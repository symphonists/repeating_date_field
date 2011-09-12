# Field: Repeating Date

A field that generates, stores and filters repeating dates.

__Version:__ 1.1  
__Date:__ 13 September 2011  
__Requirements:__ Symphony 2.2  
__Author:__ Rowan Lewis <me@rowanlewis.com>  
__GitHub Repository:__ <http://github.com/rowan-lewis/repeating_date_field>  


## Installation

1. Upload the 'repeating_date_field' folder in this archive to your Symphony
   'extensions' folder.

2. Enable it by selecting the "Field: Repeating Date", choose Enable from the
   with-selected menu, then click Apply.

3. You can now add the "Repeating Date" field to your sections.


## Features

 - Boolean searching with the `boolean` filter.
 - Regular expression searching with the `regexp` filter.
 - Partial searching with `starts-with`, `ends-with` and `contains` filters.
 - The above filters can be negated by prefixing with `not-`.
 - Text formatter and validation rule support.
 - Output grouping on handle.
 - 'Raw' output mode for unformatted data.
 - Parameter output support.
 - Limit the number of characters that can be entered.
 - Limit the number of characters shown in publish table columns.
 - Handles are always unique.


## Changelog

*Version 1.1, 13 September 2011*

 - Refactored and documented the codebase
 - Made compatible with Symphony 2.2
