# GitHub Actions workflow hygiene. Input: a .github/workflows/*.yml file.
package workflows

import rego.v1

# YAML 1.1 parsers read the bare `on:` key as boolean true.
on_value := object.get(input, "on", object.get(input, "true", null))

triggers := t if {
	is_object(on_value)
	t := object.keys(on_value)
} else := t if {
	is_array(on_value)
	t := {x | some x in on_value}
} else := {on_value}

deny contains msg if {
	"pull_request_target" in triggers
	msg := "pull_request_target runs untrusted code with repository secrets; use pull_request"
}

deny contains msg if {
	not input.permissions
	msg := "workflow must declare top-level permissions (least privilege)"
}

deny contains msg if {
	input.permissions == "write-all"
	msg := "workflow permissions must not be write-all"
}

deny contains msg if {
	some job_id, job in input.jobs
	some step in object.get(job, "steps", [])
	uses := object.get(step, "uses", "")
	uses != ""
	not startswith(uses, "./")
	not regex.match(`@(v[0-9][0-9.]*|[0-9a-f]{40})$`, uses)
	msg := sprintf("job %v: action %v must be pinned to a version tag or commit SHA", [job_id, uses])
}

deny contains msg if {
	some job_id, job in input.jobs
	not job["timeout-minutes"]
	not job.uses
	msg := sprintf("job %v must set timeout-minutes", [job_id])
}
