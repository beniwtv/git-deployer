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
---------------------

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
After you have logged in, execute the config command, which will guide you through
the configuration for the rest of Git-Deployer:

```
git-deployer config
```

After you have sucessfully configured Git-Deployer, you can check the status of your
deployments with the status command:

```
git-deployer status
```

To obtain a little bit more information about a Git project, use the info command:

```
git-deployer info <projectname>
```

You can also delete all information from Git-Deployer if you use the logout command:

```
git-deployer logout
```

3) Deployment with Git-Deployer
--------------------------------

To be able to deploy a Git repository with Git-Deployer, you must first add the project
so that Git-Deployer is made aware of the new project:

```
git-deployer add <projectname>
```

You can also remove an added Project with the remove command:

```
git-deployer remove <projectname>
```

<To be continued...>


4) More!
--------

See git-deployer -h for more commands!

[1]: https://github.com/git-deployer