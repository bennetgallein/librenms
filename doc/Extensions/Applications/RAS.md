# RAS

Reports hardware errors: memory (ECC), CPU machine checks, PCIe Advanced
Error Reporting, block I/O errors and NIC health reports.

The application reads two independent sources:

- **EDAC sysfs**, which the kernel maintains with no daemon running. This
  gives per-DIMM corrected and uncorrectable error counts and keeps working
  even if rasdaemon stops.
- **The rasdaemon database**, which holds decoded event history with
  timestamps for every error class the kernel can report.

It also reports firmware **BERT** records. These appear once per boot and
describe a fatal hardware error from the *previous* boot. When a machine dies
from a hardware fault the kernel usually logs nothing at all, so the BERT
record is often the only evidence that survives the reset.

### Requirements

Linux only, since EDAC and rasdaemon are Linux subsystems. Beyond rasdaemon
itself the script needs nothing installed: DIMM details are read from the
kernel's raw SMBIOS tables, falling back to `dmidecode` only where the kernel
was built without `CONFIG_DMI_SYSFS`. The kernel log is read with `journalctl`
where systemd is present and `dmesg` otherwise, and rasdaemon's state is taken
from `systemctl` or, failing that, from `/proc`. Each source degrades on its
own: a missing one drops its fields rather than failing the script.

### SNMP Extend

1. Install rasdaemon. It is packaged for most distributions.

```
# Debian and Ubuntu
apt install rasdaemon

# RHEL, Rocky and Alma
dnf install rasdaemon
```

2. Enable it. The daemon must run with `--record` so it writes its SQLite
   database. The packaged unit file already does this.

```
systemctl enable --now rasdaemon
systemctl enable --now ras-mc-ctl
```

3. Copy the [ras](https://github.com/librenms/librenms-agent/blob/master/snmp/ras)
   script to `/etc/snmp/`.

```
wget https://raw.githubusercontent.com/librenms/librenms-agent/master/snmp/ras -O /etc/snmp/ras
chmod +x /etc/snmp/ras
```

4. The script needs root to read the kernel log and DMI, so run it through
   sudo. Create `/etc/sudoers.d/snmpd-ras`:

```
Debian-snmp ALL=(ALL) NOPASSWD: /etc/snmp/ras
```

Use the account your SNMP daemon runs as. It is `Debian-snmp` on Debian and
Ubuntu, and `root` on RHEL derivatives, where no sudoers entry is needed.

5. Add to `/etc/snmp/snmpd.conf`:

```
extend ras /usr/bin/sudo /etc/snmp/ras
```

6. Restart snmpd.

### DIMM slot names

EDAC names DIMMs by controller coordinates, such as
`CPU_SrcID#0_MC#0_Chan#0_DIMM#0`. The script maps these to the name printed
on the board, such as `P1-DIMMA1`, so an alert identifies the slot to pull.

The mapping is read from DMI rather than assumed. Each memory device carries
a Bank Locator holding the firmware's own coordinates
(`P0_Node0_Channel1_Dimm0`) alongside the silkscreen Locator (`P1-DIMMB1`).
Boards differ in what the Node field means: on some it is the memory
controller, on others it is the socket. Both are resolved from the data, so
no board model is hardcoded.

The mapping is used only when it covers every populated EDAC DIMM with
distinct names. Otherwise the raw EDAC label is reported. A partial match
would be worse than none, because it could name the wrong DIMM. The
`label_source` field reports which was used: `dmi` or `edac`.

Two cases fall back in practice. Some firmware reports no bank locators at
all, and some EDAC drivers report a slot as populated when nothing is fitted.
The second case appears as `dimm_count` exceeding `dmi_dimm_count`, where the
firmware count is the correct one.

### Notes

EDAC error counters reset when the host reboots. Treat them as gauges: a drop
to zero means the host restarted, not that the errors were repaired.

rasdaemon creates each database table when it records the first event of that
type. A missing table means no events of that class, not a failure, so
`ras-mc-ctl --summary` reporting `no such table: mc_event` on a healthy host
is expected.
