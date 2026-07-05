#!/bin/bash
ls -t /etc/apache2/rd/backups/rd-intranet_*.conf 2>/dev/null | while read f; do
    size=$(stat -c '%s' "$f" 2>/dev/null)
    mtime=$(stat -c '%Y' "$f" 2>/dev/null)
    echo "$f|$size|$mtime"
done
