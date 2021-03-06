-- ============================================================================
-- Copyright (C) 2010 Regis Houssin  <regis@dolibarr.fr>
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 2 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program; if not, write to the Free Software
-- Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
--
-- $Id: llx_element_rang.sql,v 1.1 2010/09/06 09:38:37 hregis Exp $


create table llx_element_rang
(
  rowid           	integer AUTO_INCREMENT PRIMARY KEY,  
  fk_parent			integer NOT NULL,
  parenttype		varchar(16) NOT NULL,
  fk_child			integer NOT NULL,
  childtype			varchar(16) NOT NULL,
  rang				integer	DEFAULT 0
) type=innodb;

