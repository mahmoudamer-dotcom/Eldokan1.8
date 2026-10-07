# Eldokan shopping chat

## Placement
- Removed the selection button from desktop/mobile category navigation and the large selection banner from home.
- A floating `Ask Eldokan` button opens a nonmodal shopping chat on browsing pages. It is hidden during checkout and printing.
- Category/search prompts and the search dropdown empty state open the chat in place with their context.
- Product pages can reopen the same conversation; local product links close the chat to allow browsing. `/discover` remains an entry point that opens chat.

## Conversation
- Automatic assistant messages and customer replies, a text composer and quick replies.
- Actual catalog departments, intended use, a positive EGP budget, optional keyword, sort/discount preference and available category specifications.
- Products appear as compact chat cards with actual prices, budget explanations, detail links and comparison actions. More result pages, budget/specification edits and a clear/restart action are available.
- The assistant is a guided selector, not an AI language model or a human support agent. Intended use guides choices and does not certify suitability.

## Data and lifecycle
- Existing category, category-filter and product SDK resources through the same-origin BFF. No new endpoint, API key or external chat service.
- Money.amount is converted from minor units using decimals. Only in-stock EGP products within budget are shown; variant prices may be starting prices and shipping is excluded.
- Conversation state survives normal storefront navigation through a provider in the root layout. The transcript is not persisted after reload.
- Versioned selection requirements remain in sessionStorage in the same tab for up to 24 hours; resume reloads category/specifications and asks for fresh results. Missing saved specifications are disclosed.
- API errors have retry actions; missing specifications can be skipped. Storage failure does not block conversation.
- Comparison opens its existing native dialog above chat. Chat does not trap focus or lock background scrolling. Close/Escape restores focus where possible.
- No browser visual preview, new automated tests or real payment were performed. Shipping/payment backend blockers remain separate.