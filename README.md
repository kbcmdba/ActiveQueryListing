# MySQL Active Query Listing Tool (AQL)

Welcome to the MySQL Active Query Listing (AQL) tool. This tool provides
users with a listing of active queries on a (set of) MySQL server(s). This
PHP-based tool shows the list of queries running on a set of servers in a
color-coded format showing the longest-running queries at the top of the
list.

Please note that this software is somewhere between alpha and beta quality at
this time.

## Before using this tool:

Before using this tool, you'll need to install PHP 7.2 or greater. You'll
also need to run the following composer command:

```
composer install
```

If you don't have a xAMP (like WAMP, MAMP, or LAMP) stack or Symphony /
composer installed, Google is your friend.  :-) There are lots of good guides
out there on how to install these tools. I won't reproduce that excellent work
here. I personally like XAMPP by ApacheFriends. For more information, see
https://www.apachefriends.org/download.html

## Configuring AQL

Your installation comes with a config_sample.xml file. This file needs to be
installed in /etc/aql_config.xml. It should be readable only by the web server
in order to protect it from prying eyes. Protecting this file from prying eyes
is browser-dependent so that's the reason for asking it be put in /etc.

There are three parts to this configuration file that you need to pay special
attention to. DbPass is the password you're giving AQL in order to access
servers. In the code above, it's the "SomethingComplicated" bit. This will need
to be run on all your MySQL servers to AQL can access the server and get the
output of SHOW PROCESSLIST.

The next thing you'll need to pay special attention to is the
issueTrackerBaseUrl. Configuring this can be a bit of a project, but once it's
set up properly, it makes filing issues against a particular query very simple.

Finally, roQueryPart needs to be configured to detect when a MySQL server is in
read-only mode. For most installations, you can leave this alone. If you run an
older version of MySQL, you may need to adjust this to suit your installation's
needs.

## Setting up the MySQL database on the "configuration" server

In order to use AQL, you'll need to set up the database that tells AQL where
all the hosts will live as well as what the acceptable query thresholds are.
To do this, simply play the setup_db.sql file into your configuration master
database. It will wipe out the database named aql_db, then re-create it with
template data.

The user and password you'll set up in /etc/aql_config.xml should be consistent
across all your MySQL instances. The user will need the following on all the
instances that AQL will manage (I'll assume you'll use aql_app for the user):

```
-- You should adjust these lines to meet your needs.
-- Minimally, you shoud at least change the password. I recommend also changing
-- the user and host mask (%) so you don't leave yourself overly vulnerable to
-- a denial of service attack. Note that some more recent systems require
-- SUPER privilege in order to kill processes that are owned by others. This
-- doesn't prevent the application from killing processes when the authorized
-- user has the appropriate privileges.
CREATE USER 'aql_app'@'%' IDENTIFIED BY 'SomethingComplicated' ; 
GRANT PROCESS, REPLICATION CLIENT ON *.* TO 'aql_app'@'%' ;
GRANT ALL ON aql_db.* TO 'aql_app'@'%' ;
CREATE DATABASE IF NOT EXISTS aql_db ;
```

## Note about the "second" instance

During testing, we use row four from the host table as a replication slave of
row 1.

While setting up a replication instance is beyond the scope of these
instructions, row four of the host database assumes that you have a
replication slave set up on port 3307. If you want to ignore that "system,"
simply change the decommissioned setting to 1 and should_monitor to 0.

## SELinux Installation Tips for Fedora/Redhat/CentOS

In order to allow this program to run under Fedora-based systems, it's
important to either turn off SELinux completely (yuck), or to make it possible
for programs running under your web server to connect to the database and read
files stored in the web server's directory. Older versions of Fedora Linux
required these commands.

```
sudo setsebool -P httpd_can_network_connect_db 1
sudo setsebool -P httpd_read_user_content 1
```

For more information on ActiveQueryListing, please see:

https://github.com/kbcmdba/ActiveQueryListing/

