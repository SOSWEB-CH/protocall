-- ===================================================================
-- Copyright (C) 2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
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
-- $Id: llx_prelevement_lignes.sql,v 1.1 2009/10/07 18:18:08 eldy Exp $
-- ===================================================================

create table llx_prelevement_lignes
(
  rowid               integer AUTO_INCREMENT PRIMARY KEY,
  fk_prelevement_bons integer,
  fk_soc              integer NOT NULL,
  statut              smallint DEFAULT 0,

  client_nom          varchar(255),
  amount              real DEFAULT 0,
  code_banque         varchar(7),
  code_guichet        varchar(6),
  number              varchar(255),
  cle_rib             varchar(5),

  note                text

)type=innodb;