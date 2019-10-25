# MySQL Active Query Listing Tool (AQL)

Welcome to the MySQL Active Query Listing (AQL) tool. This tool provides
users with a listing of active queries on a (set of) MySQL server(s). This
PHP-based tool shows the list of queries running on a set of servers in a
color-coded format showing the longest-running queries at the top of the
list.

Please note that this software is somewhere between alpha and beta quality at
this time.

## Before using this tool:

Before using this tool, you'll need to run the following composer command:

```
composer install
```

If you don't have composer installed, Google is your friend. :-) There are
lots of good guides out there  on how to install Symphony / Comnposer. I
won't reproduce that excellent work here.

## SELinux Installation Tips for Fedora/Redhat/CentOS

In order to allow this program to run under Fedora-based systems, it's
important to either turn off SELinux completely (yuck), or to make it possible
for programs running under your web server to connect to the database and read
files stored in the web server's directory. One way to do this is to tell
SELinux that the web server can connect to databases and to allow it to read
files flagged as "user-content" :

```
sudo setsebool -P httpd_can_network_connect_db 1
sudo setsebool -P httpd_read_user_content 1
```

For more information on this tool, please see:

https://github.com/kbcmdba/ActiveQueryListing/

