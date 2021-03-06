-- ========================================================================
-- Copyright (C) 2001-2002,2004 Rodolphe Quiedeville <rodolphe@quiedeville.org>
-- Copyright (C) 2004-2010      Laurent Destailleur  <eldy@users.sourceforge.net>
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
-- $Id: llx_c_actioncomm.sql,v 1.2 2010/07/01 21:57:06 eldy Exp $
-- ========================================================================

create table llx_c_actioncomm
(
  id         integer     PRIMARY KEY,
  code       varchar(12) UNIQUE NOT NULL,
  type       varchar(10) DEFAULT 'system' NOT NULL,
  libelle    varchar(48) NOT NULL,
  module	 varchar(16) DEFAULT NULL,
  active     tinyint DEFAULT 1  NOT NULL,
  todo       tinyint
)type=innodb;
