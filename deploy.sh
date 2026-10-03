#!/bin/bash
# deploy.sh — mirror this project to the StackCP FTP host.
# Run this yourself: ./deploy.sh
# You will be prompted for the FTP password interactively.

set -e

FTP_HOST="ftp.gb.stackcp.com"
FTP_USER="streamy@labofsolutions.com"

lftp -u "$FTP_USER" "$FTP_HOST" -e "
mirror -R --verbose \
  --exclude-glob .git/ \
  --exclude-glob .gitignore \
  --exclude-glob .env \
  --exclude-glob db/*.sqlite \
  --exclude-glob videos/ \
  --exclude-glob thumbnails/ \
  --exclude-glob temp_uploads/ \
  --exclude-glob ST.png \
  --exclude-glob deploy.sh \
  . /;
bye
"
