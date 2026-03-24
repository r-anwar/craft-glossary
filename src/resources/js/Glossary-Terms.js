// .glossary-helper helps us identify content blocks that we want to replace
// because we don't want to swap the entire content, only were it's necessary
const contentBlocks = document.querySelectorAll(".glossary-helper");

// Fetch all terms from the API
fetch(`/elements/glossary.${window.wmGlossaryID}.json`)
	.then((response) => {
		if (!response.ok) throw new Error(`API error: ${response.statusText}`);
		return response.json();
	})
	.then((data) => {
		const allTermsAndIds = getAllTerms(data);
		replaceTerms(allTermsAndIds);
		attachMouseEvents();
	})
	.catch((error) => {
		console.warn("Fetch error:", error);
	});

/**
 * Iterate over terms and create a popover for each term
 *
 * @param {Object} data Data from the terms API
 */
const addPopovers = (termsAndIds, id) => {
	const content = termsAndIds[2];
	const popover = document.createElement("div");

	popover.setAttribute("id", id);
	popover.setAttribute("popover", "auto");
	popover.setAttribute("class", "glossary-description");
	popover.style.positionAnchor = `--${id}`;
	popover.innerHTML = content;
	document.body.append(popover);
};

/**
 * Create an array of all terms, each with their UID
 *
 * @param {Object} data Data from the terms API
 */
const getAllTerms = (data) => {
	return Object.values(data.items).flatMap(
		({ term, synonyms, uid, easyRead }, idx) => [
			[term, uid, easyRead],
			...(synonyms
				? synonyms.split(",").map((s, index) => [s.trim(), uid, easyRead])
				: []),
		],
	);
};

/**
 * Truncate and prefix the UUID
 *
 * @param {String} str UID
 */
const truncateId = (str) => {
	return `${str.substring(0, 16)}`;
};

/**
 * Take all terms and synonyms and wrap each in a button
 *
 * @param {Object} data Data from the terms API
 */
const replaceTerms = (allTermsAndIds) => {
	// Iterate over all content blocks
	for (let i = 0; i < contentBlocks.length; i++) {
		const contentBlock = contentBlocks[i].closest(".section-content-partial");

		if (contentBlock) {
			// Iterate over all terms
			allTermsAndIds.sort((a, b) => b[0].localeCompare(a[0]));
			for (let j = 0; j < Object.keys(allTermsAndIds).length; j++) {
				const termsAndIds = allTermsAndIds[j];

				// The term
				const term = termsAndIds[0];
				// Regex that finds the term in the content

				const content = contentBlock.innerHTML.trim();
				// Replace all occurences of the term with a button
				let k = 0;

				contentBlock.innerHTML = wrapMatches(content, termsAndIds, i, j, k);
			}
		}
	}
};

const constructRegex = (term) => {
	const escaped = term.replace(/[-\/\\^$*+?.()|[\]{}]/g, "\\$&");
	const parts = escaped.split("\\-");

	const spanJoin = "(?:<\\/span>)?-(?:<span[^>]*>)?";
	const inner = parts.join(spanJoin);
	const withLeadingSpan = `(?:<span[^>]*>)?${inner}(?:<\\/span>)?`;

	return new RegExp(withLeadingSpan, "gi");
};

const wrapMatches = (html, termsAndIds, i, j, k) => {
	const term = termsAndIds[0];
	const id = termsAndIds[1];
	const regex = constructRegex(term);
	const result = [];
	let lastIndex = 0;
	let match;

	while ((match = regex.exec(html)) !== null) {
		const index = match.index;
		const before = html.slice(0, index);

		// 1. Reject if inside an HTML attribute
		const lastTagOpen = before.lastIndexOf("<");
		const lastTagClose = before.lastIndexOf(">");
		if (lastTagOpen > lastTagClose) {
			const insideTag = before.slice(lastTagOpen);
			const quoteMatches = insideTag.match(/"/g);
			if (quoteMatches && quoteMatches.length % 2 !== 0) continue;
			if (!quoteMatches) continue;
		}

		// 2. Reject if inside a <button>
		const tagPattern = /<\/?[a-z][a-z0-9]*(?:\s[^>]*)?\s*>/gi;
		const allTagsBefore = [...before.matchAll(tagPattern)];
		const stack = [];
		for (const t of allTagsBefore) {
			const tag = t[0];
			const isClosing = tag.startsWith("</");
			const tagName = tag.match(/<\/?([a-z][a-z0-9]*)/i)?.[1]?.toLowerCase();
			if (!isClosing) stack.push(tagName);
			else {
				const last = stack.lastIndexOf(tagName);
				if (last !== -1) stack.splice(last, 1);
			}
		}
		if (stack.includes("button")) continue;

		let prefix = `term-${i}-${j}-${k}-${index}`;
		const id = truncateId(termsAndIds[1]);
		addPopovers(termsAndIds, `${prefix}-${id}`);

		// Wrap the match in a button
		result.push(html.slice(lastIndex, index));
		result.push(
			`<button class="glossary-term" style="anchor-name: --${prefix}-${id}" popovertarget="${prefix}-${id}">${match[0]}</button>`,
		);
		lastIndex = index + match[0].length;
		k++;
	}

	result.push(html.slice(lastIndex));
	return result.join("");
};

/**
 * Toggle popovers on mouse over
 */
const attachMouseEvents = () => {
	const glossaryTerms = document.querySelectorAll(".glossary-term");
	let timeoutID;

	glossaryTerms.forEach((trigger) => {
		const targetId = trigger.getAttribute("popovertarget");
		const popover = document.getElementById(targetId);

		if (!popover) return;

		// Zeige Popover beim Hover oder Fokus
		trigger.addEventListener("mouseenter", () => {
			popover.showPopover();
		});

		trigger.addEventListener("mouseleave", () => {
			clearTimeout(timeoutID);
			timeoutID = setTimeout(function () {
				popover.hidePopover();
			}, 1000);
		});

		popover.addEventListener("mouseenter", () => {
			clearTimeout(timeoutID);
		});

		popover.addEventListener("mouseleave", () => {
			popover.hidePopover();
		});
	});
};
