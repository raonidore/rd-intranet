#!/bin/bash
ls -t /etc/samba/smb.conf.bkp.* 2>/dev/null | while read f; do
    size=$(stat -c '%s' "$f" 2>/dev/null)
    mtime=$(stat -c '%Y' "$f" 2>/dev/null)
    echo "$f|$size|$mtime"
done
