# Issue #020: Development Environment Consistency

## Priority: MEDIUM 🔶

## Description
Development team members are working with inconsistent development environments, leading to "works on my machine" problems, difficult onboarding, and deployment issues. Standardizing development environments will improve productivity, reduce bugs, and ensure consistent behavior across all development stages.

## Problem Analysis
- Developers using different versions of languages, frameworks, and tools
- Inconsistent database schemas and test data across environments
- Manual environment setup prone to errors and omissions
- Different operating systems and development tool configurations
- Missing or outdated development environment documentation
- No automated environment validation or health checks

## Impact Analysis
- **Severity**: MEDIUM
- **Development Velocity**: High - Environment issues slow down development
- **Bug Introduction**: High - Environment differences cause production bugs
- **Onboarding Time**: High - New developers take longer to get productive
- **Collaboration**: High - Team members can't easily help each other
- **CI/CD Reliability**: Medium - Pipeline failures due to environment differences

## Current Environment Issues
- PHP version differences between developers (7.4 vs 8.0 vs 8.1)
- Database version inconsistencies (MySQL 5.7 vs 8.0)
- Different development tools and IDE configurations
- Inconsistent dependency versions and package management
- Missing or outdated local configuration files

## Proposed Solution
Implement standardized development environment management:
1. Containerized development environment with Docker
2. Infrastructure as Code for local development setup
3. Automated environment provisioning and validation
4. Standardized development tools and configurations
5. Environment documentation and onboarding automation

## Implementation Steps

### Phase 1: Environment Audit and Requirements (Week 1)
- [ ] Audit current development environments and toolchains
- [ ] Document environment dependencies and requirements
- [ ] Identify common issues and pain points
- [ ] Define standard development environment specification
- [ ] Create environment compatibility matrix

### Phase 2: Docker Development Environment (Week 1-2)
- [ ] Create Docker containers for application services
- [ ] Set up docker-compose for local development stack
- [ ] Configure volume mounting for live code reloading
- [ ] Implement database seeding and test data management
- [ ] Create development-specific Docker configurations

### Phase 3: Development Tools Standardization (Week 2-3)
- [ ] Create standardized IDE configurations (VS Code settings)
- [ ] Set up consistent linting and formatting rules
- [ ] Implement Git hooks for code quality enforcement
- [ ] Configure debugging tools and environment
- [ ] Create development utility scripts and commands

### Phase 4: Automated Environment Setup (Week 3-4)
- [ ] Create automated environment setup scripts
- [ ] Implement environment health check and validation
- [ ] Set up dependency management and version locking
- [ ] Create environment reset and cleanup procedures
- [ ] Add environment troubleshooting and repair tools

### Phase 5: Documentation and Onboarding (Week 4-5)
- [ ] Create comprehensive development environment documentation
- [ ] Implement automated onboarding checklist and validation
- [ ] Create video tutorials and setup guides
- [ ] Set up development environment FAQ and troubleshooting
- [ ] Create environment update and maintenance procedures

### Phase 6: Testing and Rollout (Week 5-6)
- [ ] Test environment setup on different operating systems
- [ ] Validate environment consistency across team members
- [ ] Create environment performance benchmarks
- [ ] Roll out standardized environment to development team
- [ ] Collect feedback and iterate on improvements

## Success Criteria
- [ ] All developers using identical development environments
- [ ] New developer onboarding time reduced to <2 hours
- [ ] Zero "works on my machine" issues in pull requests
- [ ] Automated environment setup and validation working
- [ ] Development environment documentation complete and maintained
- [ ] Environment setup works consistently across operating systems

## Technical Requirements
- **Containerization**: Docker and Docker Compose
- **Version Control**: Git with standardized hooks and configurations
- **IDE Configuration**: VS Code with team-shared settings
- **Package Management**: Composer, npm with lock files
- **Database**: Consistent database version with seeded test data

## Docker Development Environment

### Docker Compose Configuration
```yaml
# docker-compose.yml
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile.dev
    ports:
      - "8080:80"
    volumes:
      - ./src:/var/www/html/src
      - ./config:/var/www/html/config
      - ./public:/var/www/html/public
    environment:
      - APP_ENV=development
      - DB_HOST=database
      - DB_NAME=aze_gemini_dev
      - DB_USER=dev_user
      - DB_PASS=dev_password
    depends_on:
      - database
      - redis
    networks:
      - aze-network

  database:
    image: mysql:8.0
    ports:
      - "3306:3306"
    environment:
      - MYSQL_ROOT_PASSWORD=root_password
      - MYSQL_DATABASE=aze_gemini_dev
      - MYSQL_USER=dev_user
      - MYSQL_PASSWORD=dev_password
    volumes:
      - mysql_data:/var/lib/mysql
      - ./database/seeds:/docker-entrypoint-initdb.d
    networks:
      - aze-network

  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"
    networks:
      - aze-network

  phpmyadmin:
    image: phpmyadmin/phpmyadmin
    ports:
      - "8081:80"
    environment:
      - PMA_HOST=database
      - PMA_USER=dev_user
      - PMA_PASSWORD=dev_password
    depends_on:
      - database
    networks:
      - aze-network

volumes:
  mysql_data:

networks:
  aze-network:
    driver: bridge
```

### Development Dockerfile
```dockerfile
# Dockerfile.dev
FROM php:8.1-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    nodejs \
    npm

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Install dependencies
RUN composer install --no-interaction --no-plugins --no-scripts
RUN npm install

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy Apache configuration
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
```

## Automated Environment Setup Script
```bash
#!/bin/bash
# setup-dev-environment.sh

set -e

echo "🚀 Setting up AZE Gemini development environment..."

# Check prerequisites
check_prerequisites() {
    echo "📋 Checking prerequisites..."
    
    if ! command -v docker &> /dev/null; then
        echo "❌ Docker is not installed. Please install Docker first."
        exit 1
    fi
    
    if ! command -v docker-compose &> /dev/null; then
        echo "❌ Docker Compose is not installed. Please install Docker Compose first."
        exit 1
    fi
    
    if ! command -v git &> /dev/null; then
        echo "❌ Git is not installed. Please install Git first."
        exit 1
    fi
    
    echo "✅ Prerequisites check passed!"
}

# Setup environment files
setup_environment() {
    echo "⚙️ Setting up environment configuration..."
    
    if [ ! -f .env ]; then
        cp .env.example .env
        echo "✅ Created .env file from template"
    else
        echo "ℹ️ .env file already exists"
    fi
}

# Build and start containers
start_containers() {
    echo "🐳 Building and starting Docker containers..."
    
    docker-compose build
    docker-compose up -d
    
    echo "✅ Containers started successfully!"
}

# Wait for services to be ready
wait_for_services() {
    echo "⏳ Waiting for services to be ready..."
    
    # Wait for database
    while ! docker-compose exec -T database mysqladmin ping -h"localhost" --silent; do
        echo "Waiting for database connection..."
        sleep 2
    done
    
    echo "✅ Database is ready!"
}

# Install dependencies and setup application
setup_application() {
    echo "📦 Installing dependencies and setting up application..."
    
    # Install PHP dependencies
    docker-compose exec -T app composer install
    
    # Install Node.js dependencies
    docker-compose exec -T app npm install
    
    # Run database migrations
    docker-compose exec -T app php bin/console doctrine:migrations:migrate --no-interaction
    
    # Seed database with test data
    docker-compose exec -T app php bin/console doctrine:fixtures:load --no-interaction
    
    # Build frontend assets
    docker-compose exec -T app npm run build
    
    echo "✅ Application setup completed!"
}

# Validate environment
validate_environment() {
    echo "🔍 Validating development environment..."
    
    # Check if application is responding
    if curl -f http://localhost:8080/health > /dev/null 2>&1; then
        echo "✅ Application is responding at http://localhost:8080"
    else
        echo "❌ Application health check failed"
        exit 1
    fi
    
    # Check database connection
    if docker-compose exec -T app php bin/console doctrine:query:sql "SELECT 1" > /dev/null 2>&1; then
        echo "✅ Database connection is working"
    else
        echo "❌ Database connection failed"
        exit 1
    fi
    
    echo "✅ Environment validation passed!"
}

# Main execution
main() {
    check_prerequisites
    setup_environment
    start_containers
    wait_for_services
    setup_application
    validate_environment
    
    echo ""
    echo "🎉 Development environment setup completed successfully!"
    echo ""
    echo "🌐 Application: http://localhost:8080"
    echo "🗄️ Database Admin: http://localhost:8081"
    echo "📊 Redis: localhost:6379"
    echo ""
    echo "📚 Next steps:"
    echo "  - Open your IDE and start developing"
    echo "  - Run 'docker-compose logs' to view application logs"
    echo "  - Run 'docker-compose down' to stop the environment"
    echo "  - Check the README.md for more development commands"
}

main "$@"
```

## VS Code Configuration

### Workspace Settings
```json
{
  "editor.formatOnSave": true,
  "editor.codeActionsOnSave": {
    "source.fixAll.eslint": true,
    "source.fixAll.phpcs": true
  },
  "php.validate.executablePath": "./vendor/bin/php",
  "php.suggest.basic": false,
  "phpcs.executablePath": "./vendor/bin/phpcs",
  "phpcs.standard": "PSR12",
  "intelephense.files.maxSize": 3000000,
  "eslint.workingDirectories": ["./"],
  "files.exclude": {
    "**/node_modules": true,
    "**/vendor": true,
    "**/.git": true,
    "**/storage/logs": true,
    "**/storage/cache": true
  },
  "search.exclude": {
    "**/node_modules": true,
    "**/vendor": true,
    "**/storage": true
  }
}
```

### Recommended Extensions
```json
{
  "recommendations": [
    "ms-vscode.vscode-docker",
    "felixfbecker.php-intellisense",
    "bmewburn.vscode-intelephense-client",
    "ms-vscode.vscode-eslint",
    "esbenp.prettier-vscode",
    "bradlc.vscode-tailwindcss",
    "ms-vscode.vscode-json",
    "redhat.vscode-yaml",
    "ms-vscode.vscode-git"
  ]
}
```

## Development Commands and Scripts
```json
{
  "scripts": {
    "dev:setup": "./scripts/setup-dev-environment.sh",
    "dev:start": "docker-compose up -d",
    "dev:stop": "docker-compose down",
    "dev:reset": "docker-compose down -v && ./scripts/setup-dev-environment.sh",
    "dev:logs": "docker-compose logs -f",
    "dev:shell": "docker-compose exec app bash",
    "dev:test": "docker-compose exec app ./vendor/bin/phpunit",
    "dev:lint": "docker-compose exec app ./vendor/bin/phpcs",
    "dev:format": "docker-compose exec app ./vendor/bin/phpcbf"
  }
}
```

## Environment Health Check
```php
<?php
// scripts/health-check.php

class EnvironmentHealthCheck {
    private array $checks = [];
    
    public function runAllChecks(): bool {
        $this->checkPhpVersion();
        $this->checkDatabaseConnection();
        $this->checkRedisConnection();
        $this->checkFilePermissions();
        $this->checkRequiredExtensions();
        
        return $this->allChecksPassed();
    }
    
    private function checkPhpVersion(): void {
        $required = '8.1';
        $current = PHP_VERSION;
        
        if (version_compare($current, $required, '>=')) {
            $this->checks['php_version'] = [
                'status' => 'pass',
                'message' => "PHP version {$current} meets requirement {$required}+"
            ];
        } else {
            $this->checks['php_version'] = [
                'status' => 'fail',
                'message' => "PHP version {$current} does not meet requirement {$required}+"
            ];
        }
    }
    
    private function checkDatabaseConnection(): void {
        try {
            $pdo = new PDO(
                'mysql:host=database;dbname=aze_gemini_dev',
                'dev_user',
                'dev_password'
            );
            
            $this->checks['database'] = [
                'status' => 'pass',
                'message' => 'Database connection successful'
            ];
        } catch (PDOException $e) {
            $this->checks['database'] = [
                'status' => 'fail',
                'message' => 'Database connection failed: ' . $e->getMessage()
            ];
        }
    }
    
    public function displayResults(): void {
        echo "\n🔍 Development Environment Health Check\n";
        echo "=====================================\n\n";
        
        foreach ($this->checks as $check => $result) {
            $icon = $result['status'] === 'pass' ? '✅' : '❌';
            echo "{$icon} {$check}: {$result['message']}\n";
        }
        
        echo "\n";
        
        if ($this->allChecksPassed()) {
            echo "🎉 All checks passed! Environment is ready for development.\n";
        } else {
            echo "⚠️ Some checks failed. Please resolve the issues above.\n";
            exit(1);
        }
    }
    
    private function allChecksPassed(): bool {
        foreach ($this->checks as $result) {
            if ($result['status'] !== 'pass') {
                return false;
            }
        }
        return true;
    }
}

$healthCheck = new EnvironmentHealthCheck();
$healthCheck->runAllChecks();
$healthCheck->displayResults();
```

## Acceptance Criteria
1. All developers can set up identical environments in <2 hours
2. Docker-based development environment works on Windows, macOS, and Linux
3. Automated scripts handle environment setup and validation
4. Consistent IDE configurations and development tools
5. Environment health checks pass for all team members
6. Zero "works on my machine" issues in code reviews

## Priority Level
**MEDIUM** - Important for team productivity and code quality

## Estimated Effort
- **Implementation Time**: 5-6 weeks
- **Team Size**: 2 developers + 1 DevOps engineer
- **Dependencies**: Docker infrastructure, team coordination

## Implementation Cost
- **Development Time**: 200-250 hours
- **Documentation**: 40-60 hours
- **Testing across platforms**: 60-80 hours
- **Training and rollout**: $2,000-3,000

## Labels
`development`, `environment`, `docker`, `medium-priority`, `productivity`

## Related Issues
- Issue #010: Infrastructure as Code Implementation
- Issue #019: Configuration Management Standardization
- Issue #002: Missing Test Coverage - Critical Security Risk

## Expected Benefits
### Development Productivity
- 50% reduction in environment-related issues
- 80% faster onboarding for new developers
- Consistent development experience across team

### Code Quality
- Elimination of environment-specific bugs
- Consistent testing and validation
- Improved collaboration and code reviews

### Operational Benefits
- Predictable deployment behavior
- Reduced support burden for environment issues
- Better CI/CD pipeline reliability