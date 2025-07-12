# SQL global class

This class provides a global SQL interface for executing queries and managing database connections.

## Usage

To use the SQL global class, simply call its methods from anywhere in your application. For example:

```php
require_once 'class.sql.php';

$_SQL = new MyQuery();
$result = $_SQL->result("SELECT * FROM users");
```

