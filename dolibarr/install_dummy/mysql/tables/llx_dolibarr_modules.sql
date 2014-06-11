-- ========================================================================
-- Copyright (C) 2005      Rodolphe Quiedeville <rodolphe@quiedeville.org>
-- Copyright (C) 2005-2009 Regis Houssin        <regis@dolibarr.fr>
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
-- $Id: llx_dolibarr_modules.sql,v 1.1 2009/10/07 18:18:07 eldy Exp $
-- ========================================================================

create table llx_dolibarr_modules
(
  numero         integer,
  entity         integer     DEFAULT 1 NOT NULL,	-- multi company id
  active         tinyint     DEFAULT 0 NOT NULL,
  active_date    datetime    NOT NULL,
  active_version varchar(25) NOT NULL

)type=innodb;


