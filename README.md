# Cisco UCS Plugin

This plugin provides a high level view of the Chassis and Blades that are associated with each Fabric Interconnect.
You can see the profile name and status of each blade.  When you hover over a blade you can see serial numbers
and other hardware specifications of that blade.  It is not the intention of this plugin to provide extensive abilities within the UCS system.
It is merely to gather basic information about the UCS environment and provide a quick overall status within ONA.

## Install

  * Download the archive and place it in your $ONABASE/www/local/plugins directory, the directory must be named `cisco_ucs_stats`
  * Make the plugin directory owned by your webserver user I.E.: `chown -R www-data /opt/ona/www/local/plugins/cisco_ucs_stats`
  * `cp /opt/ona/www/local/plugins/cisco_ucs_stats/cisco_ucs_stats.conf.php.example /opt/ona/etc/cisco_ucs_stats.conf.php`
  * Modify the `cisco_ucs_stats.conf.php` file and place the DNS name, user and password of each Fabric Interconnect.  Ideally this user is a read only user.

## Usage

You should now have a box on the ONA desktop.  It will show you data for all Fabric Interconnects listed in the .conf file.  When you hover over a blade, it will give details about that blades hardware and service profile status.

In addition you will see a count of alerts at various alert levels in the top of the plugin window.

## Future

  * More alert message detail.
  * Ability to ack alerts
  * Ability to power on/off blades
  * Ability to assign a service profile to blade
  * Clickable link to open KVMs
