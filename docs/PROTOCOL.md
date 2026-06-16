# Background Task Protocol

## Overview

This protocol defines how a **Background Task** communicates with a **Worker** to run long-lived operations (HTTP calls, data processing, etc.) without blocking the main process.

### Roles

**Background Task** (Main Process)
Responsible for spawning the Worker Process, delivering the input payload, listening to progress notifications, and managing the full lifecycle of the Worker — including clean termination in all cases (success, failure, timeout, or destruction). The Background Task is the only entry point to interact with a Worker; callers must never address the Worker Process directly.

**Worker** (Worker Process)
Performs the actual work. It receives a payload at startup, executes its logic, and notifies the Background Task at key moments: when it starts, as it progresses, and when it completes or fails. Events should carry only the information strictly necessary — the protocol is intentionally lightweight.

> **Result passing:** if the Worker produces a result that the Main Process needs to read, the recommended approach is to write it to a file and have the Main Process read that file once the task completes. This is outside the scope of the protocol itself.

---

## Protocol flow

```
  Main Process                            Worker Process
  (Background Task)                          (Worker)
        |                                        |
        |-------- spawn process ---------------->|
        |-------- JSON payload → stdin --------->|
        |-------- close stdin ------------------>|
        |                                        |
        |                         [read stdin to completion]
        |                         [begin work]
        |                                        |
        |<------- {"type": "started", ...} ------|  }
        |<------- {"type": "progress", ...} -----|  } Main Process polls
        |<------- {"type": "progress", ...} -----|  } stdout every 50ms
        |                                        |
        |      ~ exactly one of the following ~  |
        |                                        |
        |  [success]                             |
        |<------- {"type": "done"} --------------|
        |  dispatch BackgroundTaskCompletedEvent |
        |                                        |
        |  [failure]                             |
        |<------- {"type": "error", ...} --------|
        |  dispatch BackgroundTaskFailedEvent    |
        |                                        |
        |  [timeout / unexpected exit]           |
        |-------- SIGTERM ---------------------->|
        |  dispatch BackgroundTaskFailedEvent    |
        |                                        |
        |  [cancellation]                        |
        |-------- SIGTERM ---------------------->|
        |  (silent teardown, no event)           |
        |                                        |
        |-------- close pipes, release handle -->|
```

---

## Transport

Communication between the Main Process and the Worker Process uses standard Unix pipes over `stdin` and `stdout`, established at spawn time via `proc_open`.

**stdin — payload delivery (write-once)**
The Background Task writes the input payload to the Worker's `stdin` as a single JSON object, then immediately closes the pipe. This signals to the Worker that the full payload has been delivered and it can start reading. `stdin` is never reopened — there is no interactive back-and-forth after startup. If the Worker needs additional input mid-run, that is outside the scope of this protocol.

**stdout — event stream (non-blocking)**
The Worker writes events to `stdout` as newline-delimited JSON. On the parent side, `stdout` is read in non-blocking mode: the Background Task polls it every 50ms and drains all available lines on each tick. This means the parent is never blocked waiting for output, and the Worker can emit events at its own pace.

**stderr — passed through**
The Worker's `stderr` is inherited from the Main Process and is not captured. Anything the Worker writes to `stderr` (logs, PHP notices, debug output) appears directly in the terminal. The protocol does not define or interpret `stderr` content.

---

## Initialization

When the Background Task starts, it spawns the Worker Process and immediately writes the input payload to its `stdin` as a single JSON-encoded object, then closes the pipe.

The Worker blocks on `stdin` until the pipe is closed — this is how it knows the full payload has arrived. It must read `stdin` to completion before beginning its work.

The payload is a flat or nested JSON object mapping string keys to values. An empty payload is valid; the Worker receives an empty object (`{}`) and should handle that gracefully.

Keep payloads light. The protocol does not handle object serialization — prefer scalar values and plain arrays. If the Worker needs large input (a file path, a list of IDs), pass a reference (e.g. a file path or an identifier) rather than the data itself.

---

## Event stream

The Worker communicates with the Background Task by writing events to `stdout` as **newline-delimited JSON** — one JSON object per line, each terminated with `\n`.

Each event must be a JSON object with at least a `type` field (string, required). The `type` field determines how the Background Task interprets the event. The following values are defined:

| `type` | Meaning |
|---|---|
| `done` | The Worker completed successfully. **Reserved — see Terminal events.** |
| `error` | The Worker encountered a fatal error. **Reserved — see Terminal events.** |
| *(any other string)* | A progress notification. Forwarded to the caller as-is. |

Progress events may carry any additional fields alongside `type`. Keep them light — pass references or short status strings, not large payloads.

If the Background Task receives a line that is not valid JSON or not a JSON object, it treats it as a fatal protocol violation: the task is terminated immediately and a failure event is dispatched to the caller.

Empty lines are ignored.

---

## Terminal events

Terminal events signal the end of the Worker's lifecycle. Once the Background Task receives one, it cancels its polling loop, closes the Worker Process, and dispatches the corresponding event to the caller. Any output written by the Worker after a terminal event is ignored.

### `done` — success

```json
{"type": "done"}
```

Signals that the Worker completed successfully. May carry additional fields.

The Background Task dispatches `BackgroundTaskCompletedEvent`.

### `error` — failure

```json
{"type": "error", "message": "Something went wrong"}
```

Signals that the Worker encountered a fatal error and cannot continue. The `message` field is optional. May carry additional fields.

The Background Task dispatches `BackgroundTaskFailedEvent`.

### Exactly one terminal event

The Worker must emit **exactly one** terminal event — either `done` or `error` — as its last line of output. Emitting both, or neither, is a protocol violation. The Background Task handles the "neither" case via its timeout and unexpected-exit detection (see Timeout & abnormal exit).

---

## Timeout & abnormal exit

The Background Task polls the Worker's `stdout` every **50ms**. On each tick it also checks two failure conditions:

**Timeout**
If the Worker has been running for longer than the configured timeout (default: **120 seconds**) without sending a terminal event, the Background Task terminates the Worker Process and dispatches `BackgroundTaskFailedEvent` with the message `Worker timed out after Xs`.

**Unexpected exit**
If the Worker Process exits without having sent a terminal event (crash, fatal error, `exit()` call), the Background Task detects the closed pipe and dispatches `BackgroundTaskFailedEvent` with the message `Worker process exited unexpectedly`.

In both cases the Background Task is responsible for cleaning up the Worker Process — closing pipes and releasing the process handle — to ensure no zombie processes are left behind.

---

## Cancellation

The caller can cancel a running task at any time through the Background Task. The Worker Process must never be addressed directly.

On cancellation, the Background Task:
1. Cancels the polling timer
2. Closes the `stdout` pipe
3. Sends `SIGTERM` to the Worker Process if it is still running, then releases the process handle

**No event is dispatched to the caller** when a task is cancelled — it is a silent teardown. If the caller needs to react to cancellation, it should track that state itself before calling cancel.

The Worker is not notified that cancellation is happening. It will receive a broken pipe or be terminated mid-execution. Workers should therefore not rely on a graceful shutdown signal from the Background Task.
