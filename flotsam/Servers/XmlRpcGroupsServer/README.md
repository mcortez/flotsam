=Introduction=

This is a third-party groups service implementation for OpenSimulator [1].

=Requirements=

1. XmlRpcGroupsServer requires a webserver that can server PHP files and access
MySQL.  One such server is Apache.  Under Ubuntu, for instance, this will
require the packages apache2, libapache2-mod-php5 and php5-mysql.

=Installation=

1. Create a database in MySQL to host the groups database tables.

2. Run groups.sql to populate this database with a command such as 

$ mysql -u myuser -p mypassword mydb < groups.sql

3. Copy xmlrpc.php and phpxmlrpclib/ to your webserver.

4. Copy config.php.example to config.php in the same webserver directory.

5. Edit config.php so that the groups server can access the database that you
set up.  You may also want to set $groupWriteKey and $groupReadKey if other
people may be able to access the groups PHP files.

6. Edit your OpenSimulator configuration file (normally OpenSim.ini) to access
the groups server.  More instructions at [2].

=References=

[1] http://opensimulator.org
[2] http://opensimulator.org/wiki/Enabling_Groups
