# Release metadata consistency. Input: JSON from `php bin/plugin-meta.php`.
package release

import rego.v1

min_php := "7.4"

version_pattern := `^[0-9]+\.[0-9]+\.[0-9]+$`

deny contains msg if {
	not regex.match(version_pattern, object.get(input.plugin, "version", ""))
	msg := sprintf("plugin header Version %v is not X.Y.Z", [input.plugin.version])
}

deny contains msg if {
	input.plugin.version != input.readme.stable_tag
	msg := sprintf("readme Stable tag %v does not match plugin Version %v", [input.readme.stable_tag, input.plugin.version])
}

deny contains msg if {
	count(input.readme.changelog) == 0
	msg := "readme.txt has no changelog entries"
}

deny contains msg if {
	count(input.readme.changelog) > 0
	input.readme.changelog[0] != input.plugin.version
	msg := sprintf("newest changelog entry %v does not match plugin Version %v", [input.readme.changelog[0], input.plugin.version])
}

deny contains msg if {
	some i
	entry := input.readme.changelog[i]
	count([j | input.readme.changelog[j] == entry]) > 1
	msg := sprintf("changelog entry %v appears more than once", [entry])
}

deny contains msg if {
	some field in ["requires_php", "requires_at_least"]
	object.get(input.plugin, field, null) != object.get(input.readme, field, null)
	msg := sprintf("%v differs between plugin header (%v) and readme (%v)", [field, object.get(input.plugin, field, null), object.get(input.readme, field, null)])
}

deny contains msg if {
	input.plugin.runtime_min_php != input.plugin.requires_php
	msg := sprintf("runtime version check PHP %v does not match Requires PHP %v", [input.plugin.runtime_min_php, input.plugin.requires_php])
}

deny contains msg if {
	input.plugin.runtime_min_wp != input.plugin.requires_at_least
	msg := sprintf("runtime version check WordPress %v does not match Requires at least %v", [input.plugin.runtime_min_wp, input.plugin.requires_at_least])
}

deny contains msg if {
	semver.compare(normalize(input.plugin.requires_php), normalize(min_php)) < 0
	msg := sprintf("Requires PHP %v is below the supported minimum %v", [input.plugin.requires_php, min_php])
}

deny contains msg if {
	not regex.match(`^[0-9]+\.[0-9]+$`, object.get(input.readme, "tested_up_to", ""))
	msg := sprintf("readme Tested up to %v must be a major.minor WordPress version", [input.readme.tested_up_to])
}

deny contains msg if {
	input.plugin.text_domain != "central-connect"
	msg := sprintf("Text Domain %v must be central-connect", [input.plugin.text_domain])
}

normalize(v) := concat(".", array.concat(parts, array.slice(["0", "0", "0"], count(parts), 3))) if {
	parts := split(v, ".")
	count(parts) <= 3
}
