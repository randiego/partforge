# PartForge

## Documentation

See the [PartForge Guide](https://github.com/randiego/partforge/wiki) for an overview and to get started.

## Requirements

Apache 2+, php 5 versions 5.3+ or php 7.2+ (extensions: curl, gd2, mysqli, mbstrings, json; helpful settings: memory_limit=256M+, post_max_size=50M+), MySQL 5.7+ or MariaDB 10.2.3+.

## Personal Note and Background

Currently (September 2021) I am employed as a product designer at Quantum Design (qdusa.com) in San Diego.  Quantum Design builds and sells complex materials property measurement instruments
for scientists around the world.  The products that QD manufactures can contain hundreds of subassemblies built at different times by different people, weeks or months before being assembled into the final product.  Instruments of this complexity can exhibit failures or mysterious behaviors that require an engineer to do a "root cause" analysis.

I wrote PartForge (known to my coworkers as "Blue" after the server it runs on) to help with this process.
PartForge has been used by QD for a few years now and has replaced a paper-based "traveler" workflow where test data and assembly information is printed out and physically attached to assemblies, or is saved on communal file servers.   About half of QD's 200+ people use PartForge on a weekly basis, 
and about a quarter use it daily. 

Thanks go to Damon Jackson for being such an enthusiastic evangelist for PartForge in the early days Jeremy Terry for a constant stream of great suggestions (sorry I didn't get them done before you left!), Dinesh Martien for thoughtful suggestions and pushing the API development to where it was useful and, Will Neils, Andreas Amann and many more for excellent feedback and clever new ways to work with the constraints of what I wrote.  

--Randy Black

## LICENSE

PartForge is released under [GPL-3.0+](http://spdx.org/licenses/GPL-3.0+).


[partforge-wiki]: https://github.com/randiego/partforge/wiki