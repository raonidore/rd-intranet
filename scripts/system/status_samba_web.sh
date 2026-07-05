#!/bin/bash

echo "STATUS_SMBD=$(systemctl is-active smbd)"
echo "STATUS_NMBD=$(systemctl is-active nmbd)"
echo "ENABLED_SMBD=$(systemctl is-enabled smbd)"
echo "ENABLED_NMBD=$(systemctl is-enabled nmbd)"
echo "VERSION=$(smbd --version)"
echo "UPTIME=$(systemctl show smbd --property=ActiveEnterTimestamp --value)"
