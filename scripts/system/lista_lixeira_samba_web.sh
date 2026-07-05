#!/bin/bash
BASE="/srv/samba/.deleted"
mkdir -p "$BASE"

find "$BASE" -mindepth 1 -maxdepth 1 -type d -printf "%f|%p|%TY-%Tm-%Td %TH:%TM|%k\n"
