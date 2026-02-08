---
name: skill-creator
description: Guide for creating effective skills. This skill should be used when users want to create a new skill (or update an existing skill) that extends Gemini CLI's capabilities with specialized knowledge, workflows, or tool integrations.
---

# Skill Creator

This skill provides guidance for creating effective skills.

## About Skills

Skills are modular, self-contained packages that extend the agent's capabilities by providing specialized knowledge, workflows, and tools.

### What Skills Provide

1. Specialized workflows - Multi-step procedures for specific domains
2. Tool integrations - Instructions for working with specific file formats or APIs
3. Domain expertise - Specific knowledge, schemas, business logic
4. Bundled resources - Scripts, references, and assets

## Core Principles

### Concise is Key
Only add context the agent doesn't already have. Prefer concise examples over verbose explanations.

### Anatomy of a Skill

Every skill consists of a required SKILL.md file and optional bundled resources:

```
skill-name/
├── SKILL.md (required)
│   ├── YAML frontmatter metadata (required)
│   │   ├── name: (required)
│   │   └── description: (required)
│   └── Markdown instructions (required)
```

#### SKILL.md (required)

- **Frontmatter** (YAML): Contains `name` and `description`. These are the only fields read to determine *when* the skill is used.
- **Body** (Markdown): Instructions and guidance. Only loaded *after* the skill triggers.

## Skill Creation Process

1. Understand the skill with concrete examples
2. Plan reusable skill contents
3. Create the directory and SKILL.md
4. Iterate based on usage

## Writing Guidelines

- **Use imperative form.**
- **Be specific.**
- **Focus on the "How", not just the "What".**
