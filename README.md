Git-Deployer
============

Welcome to Git-Deployer! Git-Deployer is a tool which you can use to manage
your deployments from Git repositories.

This document contains information on how to download, install, and start
using Git-Deployer.

1) Installing Git-Deployer
--------------------------

To install Git-Deployer, you can download an [PHAR-archive][1], and put it
somewhere in your $PATH, for example:

```
curl -o /usr/bin/git-deployer https://github.com/git-deployer
```

2) Using Git-Deployer
-------------------------------------

First, you will need to log-in to a Git service, like GitLab or GitHub. To
know which services are available to you currently, use: 

```
git-deployer help login
```

This will list all services that are currently available in git-deployer. When
you have chosen a service, log in to it with the command:

```
git-deployer login <service>
```

The service may ask you a few questions, like the log-in user and password.
Now, see git-deployer -h for more commands!

[1]: https://github.com/git-deployer