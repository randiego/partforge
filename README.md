# partforge

## Why PartForge?

If you make, sell, or service things that have serial numbers and you want to keep track of them for days, or even forever, then PartForge might interest you. 

PartForge works like this:

* Design a home page for each of your parts and tell PartForge what style of serial number to use.  (Your parts have serial numbers right?)

* As you make or receive new parts, add their serial numbers.  Or even better, give an account to someone else and let them add the parts for you.  Every serial number now has its own home page.  It's like Facebook for parts.

* Add pictures, attachments, and comments to each part's page for the life of that part, whether it was just built, is being tested, or was returned for service.  Or even better, add accounts for everyone in your organization and have them update the part pages.

* Need structured data?  No problem, add form fields to your page definition to create text fields, dropdown boxes, yes/no selectors, and more.

* Need to know who added data or made a change to the part and when?  No problem, every part page has an event stream or feed that shows every change in chronological order from the birth of your part to the latest change.  The same redline tracking is done with your form definitions in PartForge.

* Need to keep track of assemblies of different types of parts?  This is where it gets interesting.  In addition to form fields (like dropdowns, and text boxes), you can add components to your part form definition.  Each of these components is (you guessed it) a part with its own part page and definition.  When you select a component for your part, you create a two-way link between your part page and the component part pages.

* Have detailed procedures that need to be performed?  You can add forms for procedures too.  These are like part definitions, except they belong to parts and don't have serial numbers.  

As your PartForge repository grows, new possibilities emerge, like the ability to see trends, or clustering of defects around certain batches of parts, or around certain technicians, or time periods.  
If your procedures include taking detailed pictures of your products at different assembly stages, then you can 
troubleshoot problems on a specific serial number remotely and without disassembly.   

## Getting Started

### Installation

I've tried installing PartForge on a few different platforms.  

Common theme: Apache 2+, php 5 versions 5.2.9+ or php 7.2+ (extensions: curl, gd2, mysqli, mbstrings, json; helpful settings: memory_limit=256M+, post_max_size=50M+), MySQL 5.5+ or MariaDB.

To view instructions for specific platforms, please look at the Installation section on the [Wiki][partforge-wiki].


## A Word of Caution about security

PartForge is currently intended for use inside a trusted network.  It has an API that is not password protected.  It is protected only by virtue of having no web GUI and by the fact that the API is primarily meant for viewing existing data and adding Parts and Procedures, but not changing existing Parts and Procedures.

## Personal Note and Background

Currently (6/3/2020) I am employed as a product designer at Quantum Design (qdusa.com) in San Diego.  Quantum Design builds and sells complex materials property measurement instruments
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


[partforge-wiki]: https://github.com/randiego/partforge/wiki