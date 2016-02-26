# partforge
PartForge is groupware for recording parts and assemblies by serial number and version along with associated test data and comments

PartForge is a kind of database that grew out of a need to keep track of assembly details and test data of manufactured products.
Basically, it keeps track of anything that has a serial number, 
including anything with a serial number that is composed of other things with serial numbers. 

## Key Features

* The main types of objects in PartForge are parts and procedures.  Roughly, the difference is: Parts have serial numbers and Procedures 
have dispositions (Pass, Fail, ...).

* Each Part (aka Assembly) has its own type of input form to capture structured information (input fields).  

* In addition to strucuture data (date, boolean, character, listfields, numeric fields), each Part and Procedure also 
has adhoc data like comments, photos, and file attachements.

* Each part has it's own dedicate page for each serial number, complete with a chronological timeline like FaceBook Wall, Twitter Feed, or Discourse Topic showing the 
history of the particular part.  This timeline shows when it was created, what changes
where made, who made them, when it was associated with other higher level assemblies, comments, photos, attachments, and what procedures
were performed.

* Each part can also have any number of custom test procedures that are associated with it.  A summary of these also shows up in the timeline.  
Like Part, these Procedure each have their own unique form layouts and data fields.  

* A Procedure can be associated with more than one part since Procedures often involve collections of Parts.

* In the real world, parts and assemblies are sometimes reworked, components are changed, tests are repeated, and people make useful observations.
These non-linear workflows are handled naturally with PartForge since every Part (and Procedure) is versioned automatically.  So, if you replace
a component in an assembly, or change any field value, a new version of that Part is created and this event (what, who, when) is recorded in the timeline
for the affected Part.  All previous versions are retained so you never loose the history.  

* When subassemblies are linked to higher level assemblies, you can drill both up (where used) and down (components).

* Both the part and procedure form definitions are entered using a built-in form editor where you define serial number types, data field definitions,
captions, min and max values, related components (linked parts) and the actual layout of the forms.  
These form definitions also are versioned.

* Objects like test fixtures, batches or lots of parts, testing stations, or even customers can be entered as parts and associated with 
parts and procedures in useful ways.  For example an in-house test fixture can be associated with a specific test (procedure) so that for every
test conducted, you would know what test fixture was used.  Similarly you could know all the final assemblies that were calibrated with
a specific test fixture.


## Getting Started

### Manual Installation

Sorry, this is all there is right now...

Before starting you should have Apache, php 5.2.9+ (extensions: curl, gd2, tidy; helpful settings: memory_limit=256M, post_max_size=50M), MySQL 5.5+, and preferably phpMyAdmin installed for loading the database.  On Windows, installing [WAMP](http://www.wampserver.com/en/) 
is a quick way to get all this in one shot.  

##### 1. unpack the PartForge file structure and save it someplace not necessarily within the a web viewable area.

##### 2. Create an Apache alias called partforge that points to the /public directory in the install package.

##### 3. open phpMyAdmin and create a new database called partforgedb and add a read/read access user and password (partforgeuser, partforgepw). 

##### 4. From within phpMyAdmin, make the empty partforgedb current, use the import tab to browse and load <partforge_inst_dir>/database/db_quadcopter_example.sql.
Alternatively you could load an empty image /database/db_generate.sql.

##### 5. copy the file <partforge_inst_dir>/config-sample.php to config.php and make sure you set valid values for all the '*** <values> ***' settings.

##### 6. navigate to http://localhost/partforge/ and login with user admin, admin.

## Personal Note and Background

Currently (5/21/2015) I am employed as a new product designer/scientist at Quantum Design (qdusa.com), where 200+ people build and sell laboratory instruments.
PartForge has been used by Quantum Design for 2-3 years now and is gradually replacing a paper "traveller"-based workflow where test data and assembly information 
is printed out and zip-tied to assemblies, or is saved on a communal file servers.  I wrote PartForge (known to my coworkers as "Blue" after the server name it runs on)
to provide a complete picture (almost at a glance) of the story of a completed system once it reached final test and exhibited some obscure failure.
For a complex assembly, it's hard to overstate how difficult it was to find so-called "root cause" of a failure that may be in any one of a hundred
subassemblies built at different times by different people weeks or months ago.  It was even difficult to determine if the same failure had
occured before with the same assembly.  With PartForge, it suddenly became possible to very quickly make discoveries by looking at who built something,
browsing photos, and even see the history assemblies that had been reworked and cycled back into another product.
As more and more assemblies were added to "Blue" there was a strong network effect where shared assemblies were could now be included for free by another
engineer setting up his own product structure in the system.

Thanks go to Damon Jackson for being such an enthiastic evanglist for PartForge within QD, Jeremy Terry for a constant stream of great suggestions
(sorry I didn't get them done before you left!), Dinesh Martin for pushing the API development to where it was useful and thoughtful suggestions, Will Neils, Andreas Aman and 
many more for excellent feedback and clever new ways to work with the constraints of what I wrote.  

The goofiness and bugs in PartForge are mine.


## LICENSE

PartForge is released under [GPL-3.0+](http://spdx.org/licenses/GPL-3.0+).
