<? exit ?>

; other ini files that will be used based on context and availability:
; ini.local.php
; ini.remote.php

[default]

; types are 'mysql' or 'mysqli'
type = mysqli
host = localhost
user = root
pass = ""
name = test

; naming conventions

; field types are "class", "table", "field", "primaryKey", "foreignKey"
; case may be "pascal", "camel", "lower", "upper"

case = lower
delimiter = _

class.case = pascal
class.delimiter = 

table.transform = pluralize

primaryKey.static = id

;foreignKey.suffix = _id
