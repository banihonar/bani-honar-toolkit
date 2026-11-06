# Security policy

## Reporting a problem

If you think you found a security problem in Bani Honar Toolkit, **please do not open a public issue.**

- Write to **it@banihonarpars.ir** with the subject "Security", or
- use the *Report a vulnerability* button on the **Security** tab of this repository (GitHub private reporting), if it is enabled.

Please tell us: the plugin version, your WordPress and PHP versions, what you did, what happened, and what you expected. A short proof of concept helps. We will read every report, answer as soon as we can, and credit you in the changelog if you want.

Please do not test on websites that are not yours, and do not access, change or delete data that is not yours.

## Supported versions

Only the latest release of the 1.x line receives security fixes.

## What is already checked

Every admin action checks the user's capability and a nonce; database queries are prepared; output is escaped; CSV exports are protected against spreadsheet formulas; short links accept only `http` and `https` destinations; uploaded pictures are re-saved. These checks are repeated by the automated tests in `tests/` (a stand-in for WordPress, not a real site). Real-site review by others is welcome.

## Known limits

- Page Shield is a deterrent, not a lock. A browser must receive page code, so it can always be read.
- With the default "Automatic" address source, Visitor Stats trusts proxy headers. A site that can be reached without its CDN should choose "Only the direct connection" in the settings. IP blocking checks both the header address and the connection address.
