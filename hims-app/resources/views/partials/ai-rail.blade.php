<!-- ══ AI ASSISTANT RAIL (docked right, VSCode secondary-sidebar style) ══ -->
{{--
    Replaces the old floating bubble + pop-up panel. That panel was
    position:fixed over the page, so it covered content, could not be resized,
    and had a single undivided chat log. This is a real dock: it takes its own
    column of the viewport, the page reflows around it, and it holds a list of
    separate conversations.

    Layout is driven by two custom properties on :root (public/css/hims.css):
    --hims-ai-w      the rail's width, resizable by dragging its left edge
    --hims-ai-offset how much room the page gives it — 0 when closed, and 0 on
                     narrow screens where the rail overlays instead of pushing
    The rail's own styles live in hims.css alongside the rest of the shell so
    the whole layout can be reasoned about in one place; only the behaviour is
    inline here.
--}}
<aside id="ai-rail" aria-label="AI Assistant" aria-hidden="true">
    <div id="ai-rail-resizer" role="separator" aria-orientation="vertical"
         aria-label="Resize AI panel" tabindex="0"></div>

    <div id="ai-rail-header">
        <span id="ai-rail-title"><i class="bi bi-robot"></i> AI Assistant</span>
        <button type="button" class="ai-rail-icon" id="ai-new-chat" title="New chat">
            <i class="bi bi-plus-lg"></i>
        </button>
        <button type="button" class="ai-rail-icon active" id="ai-history-btn" title="Chat history"
                aria-controls="ai-sessions" aria-expanded="true">
            <i class="bi bi-clock-history"></i>
        </button>
        <button type="button" class="ai-rail-icon" id="ai-close-btn" title="Close panel">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    {{-- Conversation history is part of the rail again, not a hidden secondary
         mode. The clock button can collapse it when somebody wants more room
         for the transcript, but every fresh browser starts with history shown. --}}
    <div id="ai-sessions">
        <div class="ai-sessions-head">
            <span>Conversations</span>
            <button type="button" id="ai-clear-all" class="ai-sessions-clear">Clear all</button>
        </div>
        <div id="ai-session-list"></div>
        <div id="ai-sessions-empty" class="ai-sessions-empty">No earlier conversations.</div>
    </div>

    <div id="ai-messages" role="log" aria-live="polite">
        <div id="ai-welcome" class="ai-msg ai">
            Hello! I'm your HIMS AI assistant. Ask me about performance, competency,
            training, succession, or anything HR-related. 🏥
        </div>
    </div>

    <div id="ai-input-row">
        <textarea id="ai-input" placeholder="Ask me anything…" rows="1"
                  aria-label="Message the AI assistant"></textarea>
        <button id="ai-send-btn" title="Send"><i class="bi bi-send-fill"></i></button>
    </div>
</aside>

{{-- Only used below the push breakpoint, where the rail overlays the page. --}}
<div id="ai-rail-backdrop"></div>
