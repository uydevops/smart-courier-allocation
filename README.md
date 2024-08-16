# Courier Assignment System

**Altf4 Yazılım**  
**Uğurcan Yaş**  
**Software Architect**

## Overview

The Courier Assignment System is a PHP-based application designed to efficiently assign delivery orders to couriers based on their proximity. The system retrieves data from a MySQL database, calculates distances between couriers and orders, and assigns the nearest available courier to each order. It also includes caching for performance optimization and error handling.

## Features

- **Distance Calculation**: Calculates the distance between couriers and orders using the Haversine formula.
- **Courier Selection**: Finds the best courier for each order based on distance and active packages.
- **Caching**: Caches courier data to improve performance.
- **Error Handling**: Provides detailed error messages and logging.

## Requirements

- PHP 7.4 or higher
- MySQL Database
- PDO Extension
- A web server (e.g., Apache, Nginx)

## Setup

1. **Clone the Repository**

   ```bash
   git clone https://github.com/yourusername/courier-assignment-system.git
   cd courier-assignment-system
