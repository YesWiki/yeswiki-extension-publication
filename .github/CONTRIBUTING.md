# Contributing rules for the repository

To contribute into the current repository https://github.com/YesWiki/yeswiki-extension-publication, you should follow these rules.

## Languages

 1. the language used for comments and code is English.
 2. the prefered language for issues and pull-request is English but French is accepted.

## Structure of the code

 - The repository is a yeswiki extension. Contributions must follow rules of YesWiki community.
   - the main CONTRIBUTING file of the community is here : https://github.com/YesWiki/yeswiki/blob/doryphore/.github/CONTRIBUTING.md
   - the rules and help to code are here : https://github.com/YesWiki/yeswiki/blob/doryphore/docs/code/README.md
   - the extension that serves of example is here : https://github.com/YesWiki/yeswiki/tree/doryphore/tools/helloworld.
   - It is preferable to follow this example to maintain the same structure in all yeswiki extensions

## Maintainers

Current maintainers of this repository are :
 - MrFlos (https://github.com/MrFlos)

Previous maintainers were:
 - J9rem (https://github.com/J9rem)
 - Thom4parisot (https://github.com/thom4parisot)

## Commits

 - Direct commits on main branch `master` can only be made by maintainers ([see list of maintaines above](#Maintainers)).
 - Proposals of change by others contributors must be done into a new branch on your fork or on the current repository if you have rights to create a new branch.
 - A pull-request must be created to ask merge from the created branch to the branch `master`
 - Only reviewed pull-request can be merged into `master` branch
 - When a reviewed pull-request is closed, the concerned branch must be deleted
 - It is preferable not to do force-push on `master` branch to keep a prettier commits history tree, but maintainers can do that to rename commits messages if not understandable.
 - When a pull-request is done, you should warn maintainers by assigning the pull-request to one maintainer.
 - If after two weeks, there is no response you can ping a maintainer on YesWiki forum : https://forum.yeswiki.net/c/support/9
 - For contributors with rights to write on the current repository, if no answer is done by a maintainer after three weeks (no contact), you can merge the changes. That's why no security checks are done on commits and push. **This rule is applicable also to change the current rule.**

## Branch names

 - for a new feature, `feat/my-feature`
 - for a fix, or an improvement of a feature, `fix/my-feature-name`
 - for a bugfix, `bugfix/bug-name`
