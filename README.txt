/*
    Upload multi files extension for eZ publish
    Copyright (C) 2010  xrow GmbH, Hanover Germany

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
*/

Developed by
Björn Dieding/Kristina Ebel ( bjoern@xrow.de/kristina@xrow.de )

This extenstion allows multi files upload via drag and drop or select and confirm. 
You can define max file size and max number of files for the upload in the class which includes this datatype.

Following runtimes can be used (define in xrowmultibinary.ini):

Flash
	This runtime supports features: jpgresize, pngresize, chunks.
Html5
	This runtime supports features: dragdrop, jpgresize, pngresize.
Silverlight
	This runtime supports features: jpgresize, pngresize, chunks. Only for IE.
	
Example for the xrowmultibinary.ini

[Settings]
Runtimes=flash
#or Runtimes=flash,html5,silverlight