-- ===================================================================
-- Copyright (C) 2005-2009 Laurent Destailleur <eldy@users.sourceforge.net>
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
-- $Id: llx_mailing_cibles.key.sql,v 1.1 2009/10/07 18:18:06 eldy Exp $
-- ===================================================================


ALTER TABLE llx_mailing_cibles ADD UNIQUE uk_mailing_cibles (fk_mailing, email);

ALTER TABLE llx_mailing_cibles ADD INDEX idx_mailing_cibles_email (email);
