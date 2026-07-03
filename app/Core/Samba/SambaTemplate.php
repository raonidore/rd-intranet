<?php

namespace App\Core\Samba;

class SambaTemplate
{
    public static function global(): string
    {
        return <<<CONF
[global]
   workgroup = WORKGROUP
   server string = SMB PMPE
   netbios name = SMB-PMPE
   security = user

   map to guest = Never

   interfaces = lo enp6s18
   bind interfaces only = yes

   log file = /var/log/samba/%m.log
   max log size = 10000
   logging = file

   server min protocol = SMB2
   client min protocol = SMB2

   load printers = no
   disable spoolss = yes
   printing = bsd
   printcap name = /dev/null

   vfs objects = acl_xattr recycle

   map acl inherit = yes
   store dos attributes = yes

   access based share enum = yes
   hide unreadable = yes

   recycle:repository = .recycle
   recycle:keeptree = yes
   recycle:versions = yes
   recycle:touch = yes

   unix charset = UTF-8
   dos charset = CP850

   follow symlinks = no
   wide links = no

CONF;
    }

    public static function share(array $share): string
    {
        $nome = $share['nome'];
        $path = $share['caminho'];
        $grupo = $share['grupo'] ?? $share['grupo_linux'] ?? '';
        $readOnly = ((int)($share['somente_leitura'] ?? 0) === 1) ? 'yes' : 'no';

        $validUsers = "@{$grupo} @ti";

        $config = <<<CONF

[$nome]
   path = $path
   browseable = yes
   read only = $readOnly

   valid users = $validUsers
   force group = $grupo

   create mask = 0660
   directory mask = 2770

   hide unreadable = yes
   hide unwriteable files = yes

CONF;

        if ((int)($share['bloqueio_extensoes'] ?? 0) === 1) {
            $config .= <<<CONF
   veto files = /*.exe/*.com/*.bat/*.cmd/*.dll/*.msi/*.scr/*.pif/*.cpl/*.ps1/*.psm1/*.vbs/*.vbe/*.js/*.jse/*.wsf/*.wsh/*.jar/*.reg/*.hta/*.lnk/*.apk/*.deb/*.rpm/*.appimage/*.sh/*.bin/
   delete veto files = yes

CONF;
        }

        return $config;
    }
}
