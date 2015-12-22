## Command Usage
wip

## Import Manager
wip

## Mapping Fields
wip

## Huge Data Transfer

Sometimes you need transfer very big data. If you have more than 1000 row a table, php says
"Fatal error: Allowed memory size of X bytes exhausted (tried to allocate Y bytes)"

Solution is simple!

Run bin/batch-import bash command.

```bash
./bin/batch-import -t 50000 --limit 100 -m connection_key -e entity_key
```
#### Batch Import Arguments

  Short | Long | Requirement | Description 
 -------|------|-------------|------------- 
  -t | --total | required | Total rows of table want you transfer.   
  -l | --limit | required | Limit for every import action. 
  -m | --map | required | Connection key in map configuration.  
  -e | --entity | required | Entity key in map configuration. 
  
  