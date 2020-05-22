# partforge

## Why PartForge?

If you make, sell, or service things that have serial numbers and you want to keep track of them for days, or even forever, then PartForge might interest you. 

PartForge works like this:

* Design a home page for each of your parts and tell PartForge what style of serial number to use.  (Your parts have serial numbers right?)

* As you make or receive new parts, add their serial numbers.  Or even better, give an account to someone else and let them add the parts for you.  Every serial number now has its own home page.  It's like Facebook for parts.

* Add pictures, attachments, and comments to each part's page for the life of that part, whether it was just built, is being tested, or was returned for service.  Or even better, add accounts for everyone in your organization and have them update the part pages.

* Need structured data?  No problem, add form fields to your page definition to create text fields, dropdown boxes, yes/no selectors, and more.

* Need to know who added data or made a change to the part and when?  No problem, every part page has an event stream or feed that shows every change in chronological order from the birth of your part to the latest change.

* Need to keep track of assemblies of different types of parts?  This is where it gets interesting.  In addition to form fields (like dropdowns, and text boxes), you can add components to your part form definition.  Each of these components is (you guessed it) a part with its own part page and definition.  When you select a component for your part, you create a two-way link between your part page and the component part pages.

* Have detailed procedures that need to be performed?  You can add forms for procedures too.  These are like part definitions, except they belong to parts and don't have serial numbers.  

As your PartForge repository grows, new possibilities emerge, like the ability to see trends, or clustering of defects around certain batches of parts, or around certain technicians, or time periods.  
If your procedures include taking detailed pictures of your products at different assembly stages, then you can 
troubleshoot problems on a specific serial number remotely and without disassembly.   

## Getting Started

### Installation

I've tried installing PartForge on a few different platforms.  Below are my notes for each specific platform.

Common theme: Apache 2+, php 5 versions 5.2.9+ or php 7.2+ (extensions: curl, gd2, mysqli; helpful settings: memory_limit=256M+, post_max_size=50M+), MySQL 5.5+ or MariaDB.

#### Installing on Centos/Redhat with shell access

This is for the case of running on an in-house server, or a hosted Virtual Private Server.  
If you are trying out PartForge, a good way to go is to install VirtualBox and download a ready-to-use virtual 
machine image (for example, see http://virtualboxes.org/images/centos/).

##### 1. Download ZIP from https://github.com/randiego/partforge/zipball/master and put it on the server.

You can unpack the PartForge file structure on a PC and upload it to your server, or if you are logged into your server with shell access:

```
wget https://github.com/randiego/partforge/zipball/master
unzip master
cd randiego-partforge-xxxxxxx    (or whatever its called)
mv partforge /var/www
```

We save it to ```/var/www``` so that the public directory is: ```/var/www/partforge/public```.  

##### 2. Create an Apache alias called partforge that points to the /public directory in the install package.

In Centos/Redhat, this can normally be done by adding the file partforge.conf (content as follows) to the ```/etc/httpd/conf.d/``` directory.

```
Alias /partforge/ "/var/www/partforge/public/" 
<Directory "/var/www/partforge/public/">
    Options Indexes FollowSymLinks MultiViews
    AllowOverride all
    Order allow,deny
    Allow from all
</Directory>
```

You may need to restart the webserver.

##### 3. Edit the /var/www/partforge/public/.htaccess file uncomment the correct section.

For use with aliases in this installation example, your .htaccess file should look like this:

```
RewriteEngine on
RewriteCond %{SCRIPT_FILENAME} !-f
RewriteCond %{SCRIPT_FILENAME} !-d
RewriteCond %{REQUEST_URI} ^/partforge
RewriteRule ^.*$ /partforge/index.php
```

##### 4. Create the database.

You can open phpMyAdmin or some other MySql management program and create a new database (select utf8_general_ci for collation) called, say, partforgedb and add a read/write access user and password (partforgeuser, partforgepw) with local access and grant all permissions on the database partfforgedb.

Or, if you have shell access, do the following:

```
mysql -u root -p
>CREATE USER 'partforgeuser'@'localhost' IDENTIFIED BY 'partforgepw';
>CREATE DATABASE partforgedb;
>GRANT ALL PRIVILEGES ON partforgedb.* TO 'partforgeuser'@'localhost';
``` 

##### 5. Browse to ```http://[host]/partforge/install.php``` and follow the steps to agree to the license and to perform checks and initialize the database and the configuration.
Note that if you get a 403 Forbidden message instead of the installer page, you may need to disable SELinux if you have that.  

##### 6. When you have completed the install.php script, you are then prompted to login at ```http://[host]/partforge/``` with login id = admin, password = admin.

#### Installing on GoDaddy shared hosting account

This will create an instance of the PartForge software at a subdomain like partforge.mydomain.com.

##### 1. Download and install PartForge files.

Click the "Download ZIP" button on https://github.com/randiego/partforge and save it to your PC.  
Open Godaddy file manager, create a folder called temp and upload the PartForge zip file to it.
Select the zip file and extract it.
Browse into the folder you just extracted and select the /partforge folder and move it to the top level (webroot).

##### 2. Create Subdomain partforge.

Use the GoDaddy control panel (Hosted Domains) to create a subdomain called partforge.mydomain.com in your account and 
have it point to "/partforge/public".  This is a folder you just created.

##### 3. Configure .htaccess files for proper redirects and security.

Protect the application files from direct access by creating the file /partforge/.htaccess containing something like this:

```
RewriteEngine On
RewriteCond %{HTTP_HOST} !partforge.mydomain.com
RewriteRule ^.*$ "http://partforge.mydomain.com/" [R,L]
```

Modify the /partforge/public/.htaccess file to look like this:

```
Options -MultiViews
RewriteEngine on
RewriteCond %{SCRIPT_FILENAME} !-f
RewriteCond %{SCRIPT_FILENAME} !-d
RewriteRule ^.*$ /index.php
```

##### 4. Create the database and user

Open the GoDaddy Database/MySQL control panel and add an empty database: partforgedb for both the DB name and user.  
Add, say, Busted@56@Sprinkles for the password.  The hostname might be something like this: partforgedb.db.3523013.hostedresource.com.

##### 5. Run the installer

Go to partforge.mydomain.com/install.php and begin.  This will verify your system is configured properly and also create a config.php file.
If the install.php script reports that your php configuration is not correct or optimal (example: the memory limit is too low), 
it may be possible to create a file php5.ini with the following contents, and place it in your webroot.
Refer to GoDaddy's documentation.
```
memory_limit = 256M;
post_max_size = 40M;
magic_quotes_gpc = Off;
```   

##### 6. Use the File Manager to rename the install.php file to install.php.done so that it can no longer be run by site users. 


## A Word of Caution about security

PartForge is currently intended for use inside a trusted network.  It has an API that is not password protected.  It is protected only by virtue of having no web GUI and by the fact that the API is primarily meant for viewing existing data and adding Parts and Procedures, but not changing existing Parts and Procedures.

## Personal Note and Background

Currently (3/9/2016) I am employed as a product designer at Quantum Design (qdusa.com) in San Diego.  Quantum Design builds and sells complex materials property measurement instruments
for scientists around the world.  The products that QD manufactures can contain hundreds of subassemblies built at different 
times by different people, weeks or months before being assembled into the final product.  Instruments of this complexity can exhibit failures or 
mysterious behaviors that require an engineer to do a "root cause" analysis.

I wrote PartForge (known to my coworkers as "Blue" after the server it runs on) to help with this process.
PartForge has been used by QD for a few years now and is gradually replacing a paper-based "traveler" workflow where test data and assembly information 
is printed out and physically attached to assemblies, or is saved on a communal file servers.   About half of QD's 200+ people use PartForge on a weekly basis, 
and about a quarter use it daily. 

Thanks go to Damon Jackson for being such an enthusiastic evangelist for PartForge within QD, Jeremy Terry for a constant stream of great suggestions
(sorry I didn't get them done before you left!), Dinesh Martien for thoughtful suggestions and pushing the API development to where it was useful and, Will Neils, Andreas Amann and 
many more for excellent feedback and clever new ways to work with the constraints of what I wrote.  

--Randy Black

## LICENSE

PartForge is released under [GPL-3.0+](http://spdx.org/licenses/GPL-3.0+).
