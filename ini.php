<? exit ?>

[default]

type = mysql
host = localhost
user = root
pass = "tasadcotw!"
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
