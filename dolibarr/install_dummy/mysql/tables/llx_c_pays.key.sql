-- ========================================================================
-- Copyright (C) 2005 Laurent Destailleur  <eldy@users.sourceforge.net>
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
-- $Id: llx_c_pays.key.sql,v 1.1 2009/10/07 18:18:07 eldy Exp $
-- ========================================================================


ALTER TABLE llx_c_pays ADD UNIQUE INDEX idx_c_pays_code (code);
ALTER TABLE llx_c_pays ADD UNIQUE INDEX idx_c_pays_code_iso (code_iso);
ALTER TABLE llx_c_pays ADD UNIQUE INDEX idx_c_pays_libelle (libelle);