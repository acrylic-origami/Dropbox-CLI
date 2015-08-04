# Dropbox-CLI
Dropbox Command Line Interface experiment powered by PHP and MySQL — obsoleted by Dropbox proprietary Python Daemon

**_NOTICE: at the current moment, it is pretty unstable. Test at own risk._**

---

##What is this madness? Why does/did it exist?

This arose from my frustrations of trying to pair Drobpox with my Linux VPS with the proprietary application. 
For the most part, it tries to be smart about pulling and pushing files that have conflicting revisions, but 
the system is not quite perfect.

##Pre-requisites

The application works assuming you have PHP >=5.3.0 with the MySQLi extension loaded, as well as MySQL >=5.3 
or MariaDB >=10.0, but it probably works just as well on earlier versions. They are simply untested.

##Installation

1. Install this in an empty directory named "Dropbox". Make sure it has sufficiently open permissions so that PHP can write and read files (for obvious reasons)
2. Sign up for a [Dropbox Developer account](https://www.dropbox.com/developers)
3. Create a new Dropbox application, and name it something wild and unique (unique being the important criterion)
4. Navigate over to your `App Console` and retrieve your app key and secret.
  1. Paste them into `creds.json` under `[ENTER APP KEY HERE]` and `[ENTER APP SECRET HERE]` respectively.
5. Generate an access token. Place this exact string in `tok.txt`.
6. Execute setup.sql on your MySQL server. It creates an empty `revs` table to keep track of your revisions.
7. Input your MySQL credentials into mysql_creds.json in their respective, obvious places.

##Usage

Execute with:

```
php /path/to/Dropbox/db-cli/main.php [pull|push|sync] {dir-1} {dir-2} ...
```
