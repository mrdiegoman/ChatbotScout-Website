General

Work as a careful senior web developer.

Before changing code:

inspect the relevant files and existing project structure
read package.json and use the package manager matching the lockfile
reuse existing components, styles, utilities, and conventions
make only changes related to the requested task

Do not change the framework, build system, package manager, or project architecture unless explicitly requested.

Never expose or commit secrets, API keys, tokens, or .env files.

Implementation
Write clear, maintainable code consistent with the existing project.
Use TypeScript when the project uses TypeScript.
Avoid any, unnecessary abstractions, duplicate components, and unrelated refactoring.
Do not add a dependency when the feature can reasonably be implemented with existing tools.
Preserve responsive behavior and accessibility.
Use semantic HTML, labels for form fields, keyboard-accessible controls, visible focus states, and appropriate image alt text.
Handle relevant loading, error, empty, disabled, and success states.
Do not claim something works unless it was actually verified.
Context7 MCP

Use Context7 when implementing or changing version-dependent APIs from external frameworks or libraries.

Examples include:

React, Next.js, Tailwind, component libraries
authentication, database, form, validation, and testing libraries
unfamiliar or recently changed APIs

First detect the installed package version. Query only documentation relevant to the current task.

Do not use Context7 for basic syntax or project-local code that can be understood from the repository.

Figma MCP

Use Figma MCP when:

the user provides a Figma link
the task asks to implement or compare a Figma design
the task explicitly asks to create or edit something in Figma

When implementing from Figma:

inspect the referenced frame or node
reuse the project's existing components and design tokens
use Figma as the source of visual intent, not application architecture
implement responsive behavior rather than copying fixed coordinates
retrieve and reuse provided assets when appropriate

When creating a design in Figma:

create editable native layers
use Auto Layout and reusable components
preserve the original design unless explicitly asked to overwrite it

If the Figma content is inaccessible, report it instead of guessing.

Playwright MCP

Use Playwright after changes affecting rendered UI, navigation, forms, interactions, responsive layout, or browser behavior.

Verify as relevant:

the affected page loads
the main user flow works
there are no new browser console errors
important controls work with keyboard and mouse
the layout works on mobile and desktop

Prefer localhost or an explicitly provided preview environment.

Never perform real payments, destructive actions, or modify production data.

For very small non-UI changes, Playwright is not required.

Verification

After implementation, run the relevant scripts available in package.json, such as:

lint
type checking
targeted tests
build

Do not invent script names. Do not suppress errors merely to make checks pass.

For UI tasks, also validate the affected flow with Playwright when possible.

Final response

Briefly report:

what was changed
main files changed
checks actually run and their results
MCP tools actually used
anything that could not be verified

Never claim that an MCP tool or test was used when it was not.