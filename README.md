# SmartFlow-Portal

Symfony 7 Skeleton APP

Requirements
------------

  * Linux / Windows (WSL2)
  * Docker CE / Docker desktop (WSL2)

The project uses CLI helper tool named `sflow`, located in the project's root directory. You must set `execute (+x)` permission on it before using it.

*Note you may want to enable the sflow CLI tool globally, except if you want to type `./` in front of it each time.*

```bash
sudo ln -s /home/change-with-username/projects/work/expertm/smartflow/sflow /usr/local/bin/sflow
```

Project initialization
------------

**Automatic:**

```bash
./sflow init
```

**Manual:**

1. Create `.env.local` file and adjust according your needs.

    ```dotenv
    # Must be present in system's hosts file
    SERVER_NAME=smartflow.projects.local

    # You may change this with another random string
    APP_SECRET=sGhCfOGaB6hdpfEBiDgnVIVPGq9Wh2K55cWh5R0LH3PjPTdp

    # Set this according the SERVER_NAME
    MERCURE_PUBLIC_URL=https://smartflow.projects.local/.well-known/mercure

    # You may change this with another random string
    MERCURE_JWT_SECRET="nxPymhvGQehgk6DYrNNtFsPHNvzLxgkuHrUtSbO0"

    MERCURE_URL=https://php/.well-known/mercure
    ```

2. Create `.env.dev.local` file and adjust according your needs.

    ```dotenv
    XDEBUG_MODE=debug
    XDEBUG_CONFIG=client_host=host.docker.internal

    # This must be adjusted with the same value from SERVER_NAME
    PHP_IDE_CONFIG=serverName=smartflow.projects.local
    ```

3. Create docker compose override file.

    ```bash
    cp compose.override.yaml.dist compose.override.yaml
    ```

4. Build the docker containers.

    ```bash
    ./sflow build
    ```
5. Start the docker containers.

    ```bash
    ./sflow up
    ```

6. Download required assets

    ```bash
    ./sflow sf importmap:install
    ```

7. Install bundled assets

    ```bash
    ./sflow sf assets:install
    ```

8. Build SASS assets

    ```bash
    ./sflow sf sass:build
    ```

9. Install developer tools.

    ```bash
    ./sflow composer install-tools
    ```

10. Check/Initialize PHPUnit.

    ```bash
    ./sflow phpunit --version
    ```
11. Load default database fixtures.

    ```bash
    ./sflow sf doctrine:fixtures:load -n
    ```
12. Done.

You can access the project's web interface on `https://[SERVER_NAME]` using default credentials: `expertm/111111` or `admin/123456`.

*Note: It's "O.K." if the browser reports that the server certificate is not trusted. Just confirm the warning and continue. The browser should not ask you again until you rebuild the container or certificate refresh is required.*

Usage
-----

You can view the available CLI utility commands for working with the project be executing:

```bash
./sflow help
```

It's a good practice to run locally `./sflow ci` and eliminate any reported issues before pushing the changes to the server.

Tests
-----

Execute this command to run tests:

```bash
./sflow phpunit
```
