// Het gesprek tot nu toe, mee naar Nova (POST nova, veld history), zodat Nova het vorige bericht kan lezen.
// Gedeeld door de website (app.js) en de app (mobile/app/main.js). Een voorstel gaat mee als tekst met wat
// ermee gebeurd is; de server houdt de laatste NovaAssistant::HISTORY_LIMIT berichten.
const LIMIT = 12;
const MAX_TEXT = 2000;
const OUTCOME = { done: ' (uitgevoerd)', cancelled: ' (niet uitgevoerd)' };

export default function novaHistory(messages) {
    return messages
        .map((message) => {
            const text = message.proposal
                ? `${message.text} Voorstel: ${message.proposal.preview}${OUTCOME[message.proposal.state] ?? ''}`
                : message.text;
            return { role: message.role === 'user' ? 'user' : 'nova', text: String(text ?? '').trim().slice(0, MAX_TEXT) };
        })
        .filter((message) => message.text !== '')
        .slice(-LIMIT);
}
