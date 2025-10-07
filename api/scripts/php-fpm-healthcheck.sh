#!/bin/sh
# Simple health check for PHP-FPM
# This script checks if PHP-FPM is running and responding

# Check if PHP-FPM process is running
if ! pgrep -f php-fpm > /dev/null; then
    echo "PHP-FPM process not running"
    exit 1
fi

# Check if port 9000 is listening
if ! netstat -ln | grep -q ":9000 "; then
    echo "PHP-FPM not listening on port 9000"
    exit 1
fi

echo "PHP-FPM is healthy"
exit 0