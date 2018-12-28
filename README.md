easyengine/log-command
======================





Quick links: [Using](#using) | [Contributing](#contributing) | [Support](#support)

## Using

~~~
ee log show [<site-name>] [--n=<line-count>] [--global] [--cli] [--all] [--nginx] [--php] [--wp] [--access] [--error]
~~~

**OPTIONS**

	[<site-name>]
		Name of website.

	[--n=<line-count>]
		Start from last number of given lines.
		---
		default: 10
		---

	[--global]
		Displays all logs including all sites, all services and cli.

	[--cli]
		Displays EasyEngine's own logs.

	[--all]
		Displays all local logs, including service logs.

	[--nginx]
		Displays nginx logs for a site.

	[--php]
		Displays php logs for a site.

	[--wp]
		Displays wp debug log for a site.

	[--access]
		Displays nginx & php access logs for a site.

	[--error]
		Displays nginx & php error logs for a site.

**EXAMPLES**

    # Show all logs.
    $ ee log show example.com --all
    watching logfile ~/easyengine/sites/example.com/logs/nginx/access.log
    watching logfile ~/easyengine/sites/example.com/logs/nginx/error.log
    watching logfile ~/easyengine/sites/example.com/logs/debug.log

    # Show debug log for site.
    $ ee log show example.com --wp
    watching logfile ~/easyengine/sites/example.com/logs/debug.log

## Contributing

We appreciate you taking the initiative to contribute to this project.

Contributing isn’t limited to just code. We encourage you to contribute in the way that best fits your abilities, by writing tutorials, giving a demo at your local meetup, helping other users with their support questions, or revising our documentation.

### Reporting a bug

Think you’ve found a bug? We’d love for you to help us get it fixed.

Before you create a new issue, you should [search existing issues](https://github.com/easyengine/log-command/issues?q=label%3Abug%20) to see if there’s an existing resolution to it, or if it’s already been fixed in a newer version.

Once you’ve done a bit of searching and discovered there isn’t an open or fixed issue for your bug, please [create a new issue](https://github.com/easyengine/log-command/issues/new). Include as much detail as you can, and clear steps to reproduce if possible.

### Creating a pull request

Want to contribute a new feature? Please first [open a new issue](https://github.com/easyengine/log-command/issues/new) to discuss whether the feature is a good fit for the project.

## Support

Github issues aren't for general support questions, but there are other venues you can try: https://easyengine.io/support/


*This README.md is generated dynamically from the project's codebase using `ee scaffold package-readme` ([doc](https://github.com/EasyEngine/scaffold-command)). To suggest changes, please submit a pull request against the corresponding part of the codebase.*
