# GLOBAL SQL CLASS PHP Library

A PHP library providing a global SQL interface for executing queries and managing common queries by misc shortcuts.

It is designed to simplify database interactions in PHP applications, allowing for easy connection management, query execution, and data manipulation.

When query is not successful, log is written to `LogDir` directory and empty result is returned in expected format to prevent breaking the application.

## Usage

```php
require_once 'class.sql.php';

$_SQL = new MyQuery();

$_SQL->connect("host", "db", "usr", "pwd");
$_SQL->LogDir = "/path/to/log/dir";

```

## Examples

### Global - main function: result($query, $values = array(), ...)

```php

// Set by directly executing a query

$result = $_SQL->result("SELECT * FROM users where id = :id", array("id" => 1));

// set parameters and then execute

$_SQL->qry("SELECT * FROM users WHERE id = :id LIMIT 1");
$_SQL->addValue("id", 1);

// Returns always an array of results even if no rows found or query fails

foreach ($_SQL->runPDO() as $row) {
    // Process each row
}  

```

### Select

```php

$row = $_SQL->GetRow("SELECT * FROM `users` WHERE `id` = 1 LIMIT 1");

// -- select all records

$_SQL->table = "users";
$_SQL->fetchArray();
    
$result = $_SQL->SelectAll();
<<<<<<< HEAD
=======

>>>>>>> c8aea43dc8c031fc76f4b16b96c37a1c3eb628cb
```

### Inserting and updating data

```php

$_SQL->table = "users";

$_SQL->values(array(    
    "name" => "John Boe",
    "email" => "boe@example.com"
));

$id = $_SQL->insert();  // receive last inserted ID

$_SQL->values(array(
    "id" => $id,
    "name" => "John Doe",
    "email" => "john@example.com"
));

$_SQL->update();

```

## Logging and debugging

The SQL class provides comprehensive logging and debugging features to help track database operations and troubleshoot issues.

### Log Directory Configuration

Set the log directory where error logs will be written:

```php
$_SQL->LogDir = "/path/to/log/dir";  // Default: "log/"
$_SQL->LogDir = FALSE;               // Disable logging completely
```

### Error Logging

When a query fails, the class automatically:
- Logs the error message, query, and values to a daily log file
- Log files are named with format: `sql-YYYY-MM-DD.log`
- Returns empty results in expected format to prevent application crashes

### Debug Functions

#### Query Debugging
```php
// Show the last executed query and values (formatted)
$_SQL->showLastQuery();
$_SQL->showLastQuery(TRUE);  // Hidden in HTML comments

// Log last query to debug array
$_SQL->logLastQuery();  // or $SQL->logQuery()

// Debug output with formatting
$_SQL->debugOutput();
$_SQL->debugOutput('', TRUE);  // Hidden output
```

#### Error and Debug Messages
```php
// Add custom debug message
$_SQL->debug("Custom debug message");

// Get all debug messages
$debugMessages = $_SQL->debug();

// Add custom error message  
$_SQL->error("Custom error message");

// Get all error messages
$errors = $_SQL->error();
```

#### Performance Timing
```php
$_SQL->startTimer();
// ... execute queries ...
$executionTime = $_SQL->stopTimer();  // Returns execution time in ms
```

#### Object Information
```php
// Display complete object state for debugging
$_SQL->info();        // Visible output
$_SQL->info(TRUE);    // Hidden in HTML comments
```

### Manual Logging
```php
// Force log current errors and debug info
$_SQL->log();                    // Use default LogDir
$_SQL->log("/custom/log/path");  // Use custom directory
```

### Example Debug Session
```php
$_SQL->startTimer();
$_SQL->debug("Starting user update process");

$_SQL->table = "users";
$_SQL->values(array("name" => "John Doe", "email" => "john@example.com"));
$_SQL->update();

$_SQL->logLastQuery();  // Log the executed query
$time = $_SQL->stopTimer();
$_SQL->debug("Update completed in {$time}");

// View all debug info
$_SQL->debug();  // Outputs all debug messages
```

