#!/bin/bash

systemctl restart smbd
systemctl restart nmbd

echo "OK"
