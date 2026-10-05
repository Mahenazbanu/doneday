    /**
     * DoneDay - local motivational message collection (no APIs, no cost).
     * ~100 messages in context categories. Selection is random but avoids
     * recent repeats using a small localStorage history.
     */
    const QUOTES = {
    "created": [
        "One thing at a time. You've got this.",
        "Good start. Momentum beats motivation.",
        "Added. Now let it be the next right thing, not everything.",
        "Small steps still move you forward.",
        "You don't need a perfect plan, just a first task.",
        "Noted. Your future self says thanks.",
        "That's on the list now — no need to hold it in your head.",
        "Every big goal starts with a single checkbox.",
        "Clear mind, one task. Nice.",
        "You've captured it. That's half the battle.",
        "One more intention set for today.",
        "Writing it down is already progress.",
        "Keep it simple. Start where you are.",
        "New task, fresh focus.",
        "You just turned a thought into a plan.",
        "Good. Now breathe, then begin.",
        "The list grows, but your focus doesn't have to.",
        "One honest task is worth ten vague ones.",
        "Captured, not carried. Well done.",
        "Today just got a little clearer."
    ],
    "completed": [
        "Nice. That's one less thing on your mind.",
        "Done and dusted. Keep going.",
        "Look at you, actually doing the things.",
        "Checked off. Feel that little win?",
        "One down. Momentum is building.",
        "That's the way. One task, one checkmark, one win.",
        "Finished! Your brain can let that one go now.",
        "Progress, quietly made.",
        "You said you'd do it — and you did.",
        "That's real progress, not just busy work.",
        "Task complete. Take a breath, then the next one.",
        "Another one off the plate.",
        "You're not just planning anymore. You're doing.",
        "Well done. That wasn't nothing.",
        "Each checkmark is proof you can trust yourself.",
        "One more thing you don't have to think about.",
        "Quietly crushing it.",
        "Done! Now, on your own terms, what's next?",
        "That's the sound of a plan working.",
        "You made it look easy.",
        "Finished tasks, lighter mind.",
        "That's a win — log it, feel it, move on.",
        "One honest step closer.",
        "The best kind of notification: done.",
        "You turned 'should' into 'did.'",
        "Another checkmark in the kept-promises-to-yourself column.",
        "Slow or fast, it's still finished.",
        "Done is done. Be proud of that.",
        "That task had no idea who it was dealing with.",
        "One less weight on your shoulders."
    ],
    "alldone": [
        "DoneDay complete. You showed up and got it done.",
        "Everything on today's list — finished. That's not luck, that's you.",
        "Full plate, empty list. Beautiful.",
        "You did every single thing you set out to do today.",
        "Today's list: conquered. Tomorrow can wait.",
        "All done. Take the evening — you've earned the quiet.",
        "Zero tasks left. Go enjoy being a person, not a to-do list.",
        "You finished the day on your own terms.",
        "Every box checked. Your focus paid off.",
        "Look at that empty list. You made that happen.",
        "Done, done, and done. Time to rest.",
        "A perfect DoneDay. Savor it.",
        "All tasks complete. Go do something that isn't a task.",
        "You didn't just get through the day — you owned it.",
        "Complete. Close the app and enjoy being done."
    ],
    "encourage": [
        "You don't need to do everything today.",
        "Some days, surviving the list is the win.",
        "It's okay to move things. That's planning, not failing.",
        "Overwhelm means you care. Narrow the scope.",
        "You are not behind. You're right here.",
        "Rest is also part of getting things done.",
        "A shorter list is a smarter list.",
        "You can't pour from an empty cup. Refill first.",
        "Doing less, better, is still doing.",
        "Give yourself the grace you'd give a friend.",
        "Not every task deserves today. Some can wait.",
        "Breathe. The list will still be there in five minutes.",
        "You're allowed to change the plan.",
        "Progress looks quiet sometimes.",
        "One task is enough if it's the right one.",
        "Heavy list? Lighten it without guilt.",
        "Your worth isn't measured in checkboxes.",
        "Slow days are still days you showed up.",
        "Let 'later' be a kind word, not a bad one.",
        "You've handled hard lists before. This one is no different."
    ],
    "empty": [
        "Nothing waiting for you. Enjoy the breathing room.",
        "A clear day is a gift. Use it or don't — it's yours.",
        "No tasks. No noise. Just today.",
        "An empty list is a chance to choose what matters.",
        "All clear. Maybe do something just for you.",
        "Zero tasks, zero pressure.",
        "Nothing scheduled. Everything possible.",
        "The list is empty — let your mind be too.",
        "No obligations on the screen. Rare and wonderful.",
        "Clear skies, clear list."
    ],
    "limit": [
        "You don't need to do everything today.",
        "A full plate is still a full plate. Let's move something, not stack more.",
        "This is your focus protecting you. Trust the limit.",
        "The list is full, not you. Choose what matters most.",
        "Five-ish things done well beats fifteen done badly."
    ]
};

    (function () {
        const KEY = 'doneday_recent_quotes';
        const HISTORY_SIZE = 12;

        function recent() {
            try { return JSON.parse(localStorage.getItem(KEY)) || []; }
            catch (e) { return []; }
        }

        function remember(text) {
            const list = recent();
            list.push(text);
            while (list.length > HISTORY_SIZE) list.shift();
            try { localStorage.setItem(KEY, JSON.stringify(list)); }
            catch (e) { /* private mode etc. — silently skip */ }
        }

        /** Pick a message for a category, avoiding very recent repeats. */
        window.pickQuote = function (category) {
            const pool = QUOTES[category] || QUOTES.encourage || [];
            if (!pool.length) return '';
            const seen = recent();
            const fresh = pool.filter(q => !seen.includes(q));
            const pickFrom = fresh.length ? fresh : pool; // all seen → allow repeats
            const quote = pickFrom[Math.floor(Math.random() * pickFrom.length)];
            remember(quote);
            return quote;
        };
    })();
