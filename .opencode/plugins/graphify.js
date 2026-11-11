// graphify OpenCode plugin (V2 plugin API)
//
// Injects a knowledge graph reminder before the first shell command of a
// session when the graph exists.
//
// IMPORTANT: keep the reminder string free of backticks and $(...) constructs.
// The hook prepends `echo "<reminder>" ; <cmd>` to the user's shell command;
// backticks inside the double-quoted echo trigger command substitution, which
// both corrupts tool output and silently executes the very graphify command we
// are only suggesting. Plain words render fine in opencode's TUI.
//
// No external dependencies: a plain default export of { id, setup } satisfies
// the V2 loader, so @opencode/plugin does not need to be installed.
import { existsSync } from "fs";
import { join } from "path";

const REMINDER =
  "[graphify] knowledge graph at graphify-out/. For focused questions, run graphify query with your question (scoped subgraph, usually much smaller than GRAPH_REPORT.md) instead of grepping raw files. Read GRAPH_REPORT.md only for broad architecture context.";

export default {
  id: "graphify",

  async setup(ctx) {
    // V2-only. The location this instance loaded is not necessarily the
    // directory of every session the hook sees, so resolve the graph relative
    // to the plugin's own location and let the existsSync check below decide.
    const directory = ctx?.location?.directory;
    let reminded = false;

    await ctx.tool.hook("execute.before", async (event) => {
      try {
        if (reminded) return;

        // V1 called the shell tool "bash"; V2 renamed it to "shell". Accept
        // both so the hook is not silently dead across host versions.
        const tool = event?.tool;
        if (tool !== "bash" && tool !== "shell") return;

        if (typeof directory === "string") {
          if (!existsSync(join(directory, "graphify-out", "graph.json"))) return;
        }

        // The tool input carries the command; mutate it in place.
        const args = event.input ?? event.args;
        if (!args || typeof args.command !== "string") return;

        // ';' not '&&' - Windows PowerShell 5.1 rejects '&&' as a statement
        // separator, breaking the first shell command of the session (#1646).
        args.command = 'echo "' + REMINDER + '" ; ' + args.command;
        reminded = true;
      } catch (err) {
        // Never break the request pipeline over a reminder.
        console.error("[graphify] reminder hook failed:", err);
      }
    });
  },
};
