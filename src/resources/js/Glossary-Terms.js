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
		const contentBlock = contentBlocks[i].closest(".section-content");

		if (contentBlock) {
			// Iterate over all terms
			for (let j = 0; j < Object.keys(allTermsAndIds).length; j++) {
				const termsAndIds = allTermsAndIds[j];

				// The term
				const term = termsAndIds[0];
				// Regex that finds the term in the content
				const regexp = constructRegex(term);
				const content = contentBlock.innerHTML.trim();
				// Replace all occurences of the term with a button
				let k = 0;
				contentBlock.innerHTML = content.replaceAll(regexp, (foundTerm) => {
					let prefix = `term-${i}-${j}-${k}`;
					const id = truncateId(termsAndIds[1]);
					addPopovers(termsAndIds, `${prefix}-${id}`);
					k++;
					return `<button class="glossary-term" style="anchor-name: --${prefix}-${id}" popovertarget="${prefix}-${id}">${foundTerm}</button>`;
				});
			}
		}
	}
};

const constructRegex = (term) => {
  const escaped = term.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
  const parts = escaped.split('\\-');
  const inner = parts.join('(?:<\\/[a-z][a-z0-9]*>)?-(?:<[^>]+>)?');

  const fullTagged = `<[^>]+>${inner}(?:<\\/[a-z][a-z0-9]*>)`;
  const plain      = `(?<!>)${inner}(?:<\\/[a-z][a-z0-9]*>)?`;

  let pattern;
  if (parts.length > 1) {
    // Build partialStart at each hyphen boundary
    const partials = parts.slice(1).map((_, i) => {
      const before = parts.slice(0, i + 1).join('(?:<\\/[a-z][a-z0-9]*>)?-(?:<[^>]+>)?');
      const after  = parts.slice(i + 1).join('(?:<\\/[a-z][a-z0-9]*>)?-(?:<[^>]+>)?');
      return `<[^>]+>${before}(?:<\\/[a-z][a-z0-9]*>)-${after}`;
    });
    pattern = `(?:${fullTagged}|${partials.join('|')}|${plain})(?!-)`;
  } else {
    pattern = `(?:${fullTagged}|${plain})(?!-)`;
  }

  return new RegExp(pattern, 'gi');
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
