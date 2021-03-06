-- ============================================================================
-- Copyright (C) 2002-2003 Rodolphe Quiedeville <rodolphe@quiedeville.org>
-- Copyright (C) 2010 Juanjo Menent             <jmenent@2byte.es>
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
-- $Id: llx_product_price.sql,v 1.5 2010/08/11 18:35:33 eldy Exp $
-- ============================================================================

create table llx_product_price
(
  rowid           integer AUTO_INCREMENT PRIMARY KEY,
  tms             timestamp,
  fk_product      integer NOT NULL,
  date_price      datetime NOT NULL,
  price_level	    smallint NULL DEFAULT 1,
  price           double(24,8) DEFAULT NULL,
  price_ttc       double(24,8) DEFAULT NULL,
  price_min              double(24,8) default NULL,
  price_min_ttc          double(24,8) default NULL,
  price_base_type varchar(3) DEFAULT 'HT',
  tva_tx          double(6,3) NOT NULL,
  recuperableonly integer NOT NULL DEFAULT '0',  
  localtax1_tx    double(6,3) DEFAULT 0,
  localtax2_tx    double(6,3) DEFAULT 0,
  fk_user_author  integer,
  tosell         tinyint DEFAULT 1
)type=innodb;

