# Bugfixes

## 2026-09-20 — Dashboard disk list missing vdb /mnt/backup

`wp-sys.sh info` only kept mounts `/` and `/www`, so CVM extra disks such as
`/dev/vdb1` mounted at `/mnt/backup` never appeared on the dashboard. The
filter now includes every `df -hP` row whose filesystem column matches `/dev/*`
(tmpfs and other virtual filesystems stay hidden). JSON field names are
unchanged: `fs`, `size`, `used`, `avail`, `use_pct`.
