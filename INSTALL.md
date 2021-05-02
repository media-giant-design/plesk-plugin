
## Microweber Plesk Plugin Installation


###Step 1 - Install Panel.ini Editor Extension
1. Open Plesk Panel
1. Go to Extensions Catalog and install "Panel.ini Editor"
1. Open the Panel.ini Editor
1. Add these lines & save

```
[license]
fileUpload=true

[ext-catalog] 
extensionUpload = true

[php] 
settings.general.open_basedir.default="none"
```

###Step 2 - Install the Plesk Plugin
1. SSH Into Your Plesk Server
2. SUDO -i as root
3. mkdir /tmp/microweber
4. cd /tmp/microweber
5. git clone <github https source>
6. cd plesk-plugin
7. Now Execute the Following Commands:

cp sbin/* /usr/bin/
