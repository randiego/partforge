# partforge
PartForge is groupware for recording parts and assemblies by serial number and version along with associated test data and comments

PartForge is a kind of database that grew out of a need to keep track of assembly details and test data of manufactured products.
Basically, it keeps track of anything that has a serial number, 
including anything with a serial number that is composed of other things with serial numbers. 

## Key Features

* The main types of objects in PartForge are Parts and Procedures.  Roughly, the difference is: Parts have serial numbers and Procedures 
have dispositions (Pass, Fail, ...).

* Each Part (aka Assembly) has its own type of input form to capture structured information (input fields).  

* In addition to strucutured data (date, boolean, character, listfields, numeric fields), each Part and Procedure also 
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

Before starting you should have Apache 2+, php 5 versions 5.2.9+ (extensions: curl, gd2, tidy, mysql (really need to change to mysqli soon); helpful settings: memory_limit=256M, post_max_size=50M), MySQL 5.5+, and preferably phpMyAdmin installed for loading the database.  On Windows, installing [WAMP](http://www.wampserver.com/en/) 
is a quick way to get all this in one shot.  

##### 1. Unpack the PartForge file structure and save it someplace not necessarily within a web viewable area.  [example: in Centos/Redhat, save it to ```/var/www``` so that public is here: ```/var/www/partforge/public```]

##### 2. Create an Apache alias called partforge that points to the /public directory in the install package.

[example: in Centos/Redhat, add the file partforge.conf (as follows) to the ```/etc/httpd/conf.d/``` directory]

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

##### 3. Open phpMyAdmin and create a new database (select utf8_general_ci for collation) called partforgedb and add a read/write access user and password (partforgeuser, partforgepw) with local access and grant all permissions on the database partfforgedb. 

##### 4. Browse to ```http://[host]/partforge/install.php``` and follow the steps agree to the license and to perform checks and initialize the database and the configuration.  

##### 5. You are then prompted to login at ```http://[host]/partforge/``` with login id = admin, password = admin. 

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
