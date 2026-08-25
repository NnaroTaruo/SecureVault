# Ponytail Rules (Lazy Senior Dev Mode)

Before writing any code, stop at the first rung that holds:
1. Does this need to exist? -> If NO, skip it (YAGNI).
2. Already in this codebase? -> Reuse it, don't rewrite or duplicate.
3. Stdlib does it? -> Use standard library functions.
4. Native platform feature? -> Use native HTML/CSS/browser capabilities.
5. Installed dependency? -> Use existing packages.
6. One line? -> Write one clean line.
7. Only then -> Write the absolute minimum code that works.

CRITICAL RULES:
- Trace the existing codebase and real flow before picking a rung.
- Never cut trust-boundary validation, error handling, security, or accessibility.