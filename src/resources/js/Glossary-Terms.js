// .glossary-helper helps us identify content blocks that we want to replace
// because we don't want to swap the entire content, only were it's necessary
const contentBlocks = document.querySelectorAll(".glossary-helper");

// Fetch all terms from the API
fetch(
	`/elements/glossary.${window.wmGlossaryID}.json`,
)
	.then((response) => {
		if (!response.ok) throw new Error(`API error: ${response.statusText}`);
		return response.json();
	})
	.then((data) => {
		addPopovers(data);
		replaceTerms(data);
	})
	.catch((error) => {
		console.warn("Fetch error:", error);
	});

/**
 * Iterate over terms and create a popover for each term
 * 
 * @param {Object} data Data from the terms API
 */
const addPopovers = (data) => {
	for (let i = 0; i < Object.keys(data.items).length; i++) {
		const item = data.items[i];
		const popover = document.createElement("div");
		popover.setAttribute("id", `term-${item.uid.substring(1, 16)}`);
		popover.setAttribute("popover", "auto");
		popover.setAttribute("class", "glossary-description");
		popover.innerHTML = item.easyRead;
		document.body.append(popover);
	}
};

/**
 * Take all terms and synonyms and wrap each in a button
 * 
 * @param {Object} data Data from the terms API
 */
const replaceTerms = (data) => {
	// Create an array of all terms, each with their UID
	const collections = Object.values(data.items).flatMap(
		({ term, synonyms, uid }) => [
			[term, uid],
			...(synonyms ? synonyms.split(",").map((s) => [s.trim(), uid]) : []),
		],
	);

	// Iterate over all content blocks
	for (let i = 0; i < contentBlocks.length; i++) {
		const contentBlock = contentBlocks[i].closest(".section-content");

		if (contentBlock) {
			// Iterate over all terms
			for (let j = 0; j < Object.keys(collections).length; j++) {
				const collection = collections[j];

				// The term
				const term = collection[0];
				// Regex that finds the term in the content
				const regexp = new RegExp(`\\b${term}\\b`, "gim");
				const content = contentBlock.innerHTML.trim();
				// Replace all occurences of the term with a button
				contentBlock.innerHTML = content.replaceAll(regexp, (x) => {
					return `<button class="glossary-term" popovertarget="term-${collection[1].substring(1, 16)}">${term}</button>`;
				});
			}
		}
	}
};
