# Filesystem and YAML safety

Stage 2 establishes the only supported path from application data to CMS files.
Controllers and future admin use cases must not concatenate filesystem paths.

## Allowed roots

`FilesystemRoot` exposes exactly three administrator-controlled roots:

- `pages/`;
- `config/`;
- `storage/`.

`SafePathResolver` resolves a validated `RelativePath` below one of these roots.
It rejects absolute paths, Windows drive paths, backslashes, `.` and `..`, empty
segments, null/control characters, non-portable characters and reserved Windows
filenames. Every existing segment is resolved with `realpath()` and checked
again against its root, which blocks symbolic-link escapes.

The web-server operating-system account remains part of the trust boundary. A
hostile user with arbitrary local filesystem write access can race normal PHP
filesystem operations; that threat requires operating-system isolation rather
than application path validation.

## Revisions and concurrent editing

Every file revision is either `missing` or the SHA-256 hash of its complete
contents. Creation supplies the expected revision `missing`. Editing supplies
the revision received during read. The writer recomputes it while holding the
exclusive lock. A mismatch throws `RevisionConflictException` without changing
the destination.

## Atomic writes

The write sequence is:

1. validate the root and relative path;
2. acquire a stable lock stored under `storage/tmp/locks/`;
3. compare the expected and current revisions;
4. create a temporary file in the destination directory;
5. write all bytes, flush and `fsync()`;
6. atomically rename the temporary file over the destination;
7. remove the temporary file after any failure.

The temporary file is deliberately created on the destination filesystem so
the rename does not cross filesystem boundaries.

## Restrictive YAML

`YamlParser` accepts a mapping at the document root and only null, booleans,
integers, finite floats, valid UTF-8 strings and nested arrays. PHP objects and
unsupported tags are not enabled. YAML aliases are rejected before expansion,
preventing alias-expansion resource attacks. Limits are applied to input bytes,
parser nesting depth and normalized node count.

`YamlFileRepository` validates the exact YAML serialization before handing it
to `AtomicFileWriter`. Invalid data therefore cannot replace the current file.

## Parsed YAML cache

Parsed mappings may be stored in two independent representations below
`storage/cache/yaml/`: JSON (`*.json`) and PHP serialized arrays
(`*.serialized`). Cache filenames are hashes of internal resource keys. A cache
entry is accepted only when its stored source revision equals the SHA-256
revision of the current YAML bytes. This avoids stale data even when
modification time and file size do not change.

Cache files are runtime-only, contain no executable PHP and may be deleted at
any time. `YAML_CACHE_JSON_ENABLED` controls JSON while
`YAML_CACHE_SERIALIZE_ENABLED` controls `serialize()`. The switches are
parallel rather than mutually exclusive. With both enabled, writes update both
files and serialized data is preferred during reads, with JSON as fallback.

Serialized entries use `unserialize(..., ['allowed_classes' => false])` and are
recursively restricted to arrays and YAML-safe scalar values. Corrupt or
object-bearing entries are discarded. Neither variable controls media
variants; media transformation and media-cache behavior belongs to
`config/setup.yml`.

`php bin/cms cache:clear` removes every generated entry below
`storage/cache/`, regardless of the cache mechanism that created it. The fixed
cache root and its root `.gitkeep` file are preserved; the command accepts no
path argument and never follows symbolic links.
