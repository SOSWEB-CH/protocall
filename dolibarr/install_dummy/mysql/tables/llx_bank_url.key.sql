-- ===================================================================
-- Copyright (C) 2005-2007 Laurent Destailleur <eldy@users.sourceforge.net>
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
-- $Id: llx_bank_url.key.sql,v 1.1 2009/10/07 18:18:04 eldy Exp $
-- ===================================================================


ALTER TABLE llx_bank_url ADD UNIQUE INDEX uk_bank_url (fk_bank,type);

--ALTER TABLE llx_bank_url ADD INDEX idx_bank_url_fk_bank (fk_bank);