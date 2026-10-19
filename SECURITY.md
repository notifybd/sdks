# Security policy

Please report vulnerabilities privately through GitHub's *Report a vulnerability* (Security tab) on
this repository, or by email to the address on your Notify account contact page. Do not open a public
issue.

Include the affected package and version, what you observed and how to reproduce it. We aim to
acknowledge within three working days.

In scope: webhook signature verification, credential handling, and anything that could cause a
message to be sent twice or to the wrong recipient. The SDKs never log API keys, webhook secrets,
recipient numbers or message bodies; a report showing otherwise is a vulnerability.
