# partforge
PartForge is groupware for recording parts and assemblies by serial number and version along with associated test data and comments

PartForge is a kind of database that grew out of a need to keep track of assembly details and test data of manufactured products.
Basically, it keeps track of anything that has a serial number, 
including anything with a serial number that is composed of other things with serial numbers. 

## Key Features

* The main types of objects in PartForge are Parts and Procedures.  Roughly, the difference is: Parts have serial numbers and Procedures 
have dispositions (Pass, Fail, ...).

* Each Part (aka Assembly) has its own type of input form to capture structured information (input fields).  

* In addition to structured data (date, boolean, character, listfields, numeric fields), each Part and Procedure also 
has adhoc data like comments, photos, and file attachements.

* Each Part has it's own dedicate page for each serial number, complete with a chronological timeline like FaceBook Wall, Twitter Feed, or Discourse Topic showing the 
history of the particular Part.  This timeline shows when it was created, what changes
where made, who made them, when it was associated with other higher level assemblies, comments, photos, attachments, and what procedures
were performed.

* Each Part can also have any number of custom test procedures that are associated with it.  A summary of these also shows up in the timeline.  
Like Parts, these Procedures each have their own unique form layouts and data fields.  

* A Procedure can be associated with more than one Part since Procedures are often performed with a combination of components (Parts).

* In the real world, parts and assemblies are sometimes reworked, components are changed, tests are repeated, and people make useful observations.
These non-linear workflows are handled naturally with PartForge since every Part (and Procedure) is versioned automatically.  So, if you replace
a component in an assembly, or change any field value, a new version of that Part is created and this event (what, who, when) is recorded in the timeline
for the affected Part.  All previous versions are retained so you never loose the history.  

* When subassemblies are linked to higher level assemblies, you can drill both up (where used) and down into components.

* Both the Part and Procedure form definitions are entered using a built-in form editor where you define serial number types, data field definitions,
captions, min and max values, related components (linked Parts) and the actual layout of the forms.  
These form definitions also are versioned.

* Objects like test fixtures, batches or lots of parts, testing stations, or even customers can be entered as Parts and associated with 
pther Parts and Procedures in useful ways.  For example an in-house test fixture can be associated with a specific test (Procedure) so that for every
test conducted, you would know what test fixture was used.  Similarly you could know all the final assemblies that were calibrated with
a specific test fixture.


## Getting Started

### Installation

I've tried installing PartForge on a few different platforms.  Below are my notes for each specific platform.

Common theme: Apache 2+, php 5 versions 5.2.9+ (extensions: curl, gd2, mysql, mysqli; helpful settings: memory_limit=256M, post_max_size=50M), MySQL 5.5+.

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
RewriteRule ^(.*)$ /partforge/index.php/$1
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
RewriteEngine on
RewriteCond %{SCRIPT_FILENAME} !-f
RewriteCond %{SCRIPT_FILENAME} !-d
RewriteRule ^(.*)$ /index.php/$1
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
memory_limit = 256M
post_max_size = 40M
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
PartForge has been used by QD for a few years now and is gradually replacing a paper-based "traveller" workflow where test data and assembly information 
is printed out and physically attached to assemblies, or is saved on a communal file servers.   About half of QD's 200+ people use PartForge on a weekly basis, 
and about a quarter use it daily. 

Thanks go to Damon Jackson for being such an enthiastic evanglist for PartForge within QD, Jeremy Terry for a constant stream of great suggestions
(sorry I didn't get them done before you left!), Dinesh Martin for thoughtful suggestions and pushing the API development to where it was useful and, Will Neils, Andreas Aman and 
many more for excellent feedback and clever new ways to work with the constraints of what I wrote.  

--Randy Black

## LICENSE

PartForge is released under [GPL-3.0+](http://spdx.org/licenses/GPL-3.0+).
