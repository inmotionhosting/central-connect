# composer.json policy. The release does not ship vendor/, so the plugin must
# not gain runtime Composer dependencies, and tooling must target PHP 7.4.
package composer

import rego.v1

deny contains msg if {
	not input.require.php
	msg := "composer.json require.php must declare the PHP minimum"
}

deny contains msg if {
	input.require.php
	input.require.php != ">=7.4"
	msg := sprintf("composer.json require.php is %v, expected >=7.4", [input.require.php])
}

deny contains msg if {
	some pkg, _ in input.require
	pkg != "php"
	not startswith(pkg, "ext-")
	msg := sprintf("runtime dependency %v is not allowed: vendor/ is not deployed", [pkg])
}

deny contains msg if {
	object.get(input, ["config", "platform", "php"], "") != "7.4"
	msg := "composer.json config.platform.php must be 7.4 so dev tools resolve for the minimum PHP"
}

deny contains msg if {
	some pkg in ["squizlabs/php_codesniffer", "wp-coding-standards/wpcs", "phpcompatibility/phpcompatibility-wp"]
	not input["require-dev"][pkg]
	msg := sprintf("require-dev must include %v", [pkg])
}
