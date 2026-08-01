<?php

/**
 * Free template catalog — byte-identical export of the legacy wpwand_dummy_datas().
 * Data only (no logic); owned by WPWand\Data\Templates. Do not hand-edit prompt strings.
 */

return array (
  'free' => 
  array (
    'Headline Generation' => 
    array (
      'title' => 'Headline Generation',
      'is_pro' => false,
      'fields' => 'Topic, Tone',
      'description' => 'Create attention-grabbing headlines for website or blog post with a specific topic.',
      'prompt' => 'I want you to act as a high quality content writer. I will type a title, or keywords via comma and you will reply with a high converting headline. It should have a hook and high potential to go viral on social media. Don\'t use quotations at the beginning and end. Don\'t use capital letter for all the words. Use sentence-case style. My keyword is{topic}.',
    ),
    'Paragraph Related to Headline' => 
    array (
      'title' => 'Paragraph Related to Headline',
      'is_pro' => false,
      'fields' => 'Topic, Tone, Word Count',
      'description' => 'Quickly generate compelling content for your headlines.',
      'prompt' => 'I want you to act as a high quality content writer. I will type a topic and you will reply with highly engaging paragraph. Keep it short and the word limit must not exceed{word count} words. The tone of the paragraph should be{tone}. My topic is{topic}.',
    ),
    'One Click Blog Post' => 
    array (
      'title' => 'One Click Blog Post',
      'markdown' => true,
      'number_of_results' => false,
      'is_pro' => false,
      'fields' => 'Topic, Keywords, Tone, Word Count',
      'description' => 'Generate complete blog posts that engage your readers.',
      'prompt' => 'Using Markdown formatting, you must write a 100% unique, creative and in a human-like style article using headings and sub-headings. The generated result must be over 4000 words. There should be minimum 20 headings and 10 sub-headings in the content. Write article on “{topic}“. Be detailed as much as possible and cover the full topic. Try to write at least 800-1000 words content for each heading or sub-heading. You must add Table of contents with links. Try to use contractions, idioms, transitional phrases, interjections, dangling modifiers, and colloquialisms, and avoiding repetitive phrases and unnatural sentence structures. The article should include SEO meta-description (must include “{topic},{keywords}” in the description), introduction, a click-worthy short title. Also, Use the seed keyword as the first H2. Always use a combination of paragraphs, lists, and tables for a better reader experience. Write at least one paragraph with heading “{topic}“. Write down at least 6 faqs with answers and conclusion. Make sure the article is plagiarism free. Don\'t forget to use a question mark (?) at the end of questions. Try not to change the original topic: “{topic}” while writing the Title. You must use “{topic},{keywords}” 2-3 times in article. Try to include “{topic}” in headings as well. Write a content which can easily pass every AI detection tools test.',
    ),
    'Blog Title' => 
    array (
      'title' => 'Blog Title',
      'is_pro' => false,
      'fields' => 'Topic, Tone',
      'description' => 'Generate titles that grab attention and increase clicks.',
      'prompt' => 'Please ignore all previous instructions. I want you to act as a high quality blog writer. I will type a title, or keywords via comma and you will reply with a high converting blog title. It should have a hook and high potential to go viral on social media. Don\'t use quotations at the beginning and end. Don\'t use capital letter for all the words. Use sentence-case style. My keyword is{topic}.',
    ),
    'Blog Outline' => 
    array (
      'title' => 'Blog Outline',
      'is_pro' => false,
      'fields' => 'Topic, Tone',
      'description' => 'Create detailed outlines that make writing blog posts a breeze.',
      'prompt' => 'I want you to act as a high quality blog writer. I will type a topic and you will reply with a complete blog outline. Each outline should be only one sentence. Writing tone should be{tone}. My topic is{topic}.',
    ),
    'Blog Intro' => 
    array (
      'title' => 'Blog Intro',
      'is_pro' => false,
      'fields' => 'Topic, Tone, Keywords',
      'description' => 'Quickly write a highly engaging intro for your blog post.',
      'prompt' => 'I want you to act as a high quality blog writer. I will type a title, and keywords via comma and you will reply with highly engaging blog intro. You must include the keywords into this intro paragraph. Use short sentences with line breaks. You must use easy to understand words. Try to use contractions, idioms, transitional phrases, interjections, dangling modifiers, and colloquialisms, and avoiding repetitive phrases and unnatural sentence structures. This intro should have a hook and high potential to go viral on social media. Don\'t use quotations at the beginning and end. Don\'t add a title. Don\'t repeat the keywords in the result. Only generate the intro paragraph. My topic is{topic} and my keywords is{keywords}.',
    ),
    'Blog Paragraph' => 
    array (
      'title' => 'Blog Paragraph',
      'is_pro' => false,
      'fields' => 'Topic, Tone, Keywords',
      'description' => 'Generate high-quality individual paragraph for your blog posts.',
      'prompt' => 'Please ignore all previous instructions. I want you to act as a high quality blog writer. I will type a title, and keywords via comma and you will reply with highly engaging paragraph which will be used in a blog post. You must include the keywords into this paragraph. Use short sentences with line breaks. You must use easy to understand words. Try to use contractions, idioms, transitional phrases, interjections, dangling modifiers, and colloquialisms, and avoiding repetitive phrases and unnatural sentence structures. Don\'t use quotations at the beginning and end. Don\'t add a title. Don\'t repeat the keywords in the result. Only generate the intro paragraph. My topic is{topic} and my keywords is{keywords}.',
    ),
    'Blog Post Writer' => 
    array (
      'title' => 'Blog Post Writer',
      'markdown' => true,
      'is_pro' => false,
      'number_of_results' => false,
      'fields' => 'Topic, Keywords, Content Text Area, Tone',
      'description' => 'Generate a SEO friendly blog post with your basic instructions',
      'prompt' => 'Using Markdown formatting, write a 100% unique, creative and human-like SEO-friendly blog post of over 3000 words using headings and sub-headings. My topic is: \'{topic}\' and you must follow this outline or description: \'{content_textarea}\'. You must write at least 2-3 paragraphs with 300-400 words content for each outline title. You must add a Table of Contents at the beginning. Try to use contractions, idioms, transitional phrases, interjections, dangling modifiers, and colloquialisms, and avoiding repetitive phrases and unnatural sentence structures. Also, use the seed keyword as the first H2. Always use a combination of paragraphs, lists, and tables for a better reader experience. Write down at least 5-7 faqs with answers and conclusion. Make sure the article is plagiarism free. Try to use \'{topic},{keywords}\' as keywords 2-3 times in the article. The final result must pass ChatGPT detection and AI content detection.',
    ),
    'Job Post' => 
    array (
      'title' => 'Job Post',
      'is_pro' => false,
      'fields' => 'Topic, Description, Tone',
      'description' => 'Create job descriptions that attract top talent.',
      'prompt' => 'Using Markdown formatting, write a 100% unique, creative and in a human-like style job post of minimum{word count} words using headings and sub-headings. Writing tone should be{tone}. Desired job post topic is: \'{topic}\'. More details of this job post:{description}. Include Responsibilities, Requirements, Benefits inside this job post using list format.',
    ),
    'Product Description' => 
    array (
      'title' => 'Product Description',
      'is_pro' => false,
      'fields' => 'Name, Description, Tone',
      'description' => 'Generate compelling product descriptions for your online store.',
      'prompt' => 'I want you to act as a high quality content writer. I will type a Product name, and short description and you will reply with highly engaging Product Description which will be used to sell this product. This description must have hooks so that readers feel connected. You must include features, benefits of using this product using paragraphs & bullet points. Writing tone should be{tone}. My product name is{name}. And a short details about my product is:{description}.',
    ),
    'Linkedin Post' => 
    array (
      'title' => 'Linkedin Post',
      'is_pro' => false,
      'fields' => 'Topic',
      'description' => 'Generate a highly engaging post for Linkedin',
      'prompt' => 'I want you to act as a high quality content writer. I will type a short topic and you will reply with a high converting social media post. This content will be used on Linkedin. Linkedin is professional networking site. So, the content should be professionl. It should have a hook and high potential to go viral on social media. Don\'t use quotations at the beginning and end. Use sentence-case style. My topic is{topic}.',
    ),
    'Facebook Post' => 
    array (
      'title' => 'Facebook Post',
      'is_pro' => false,
      'fields' => 'Topic',
      'description' => 'Generate a highly engaging post for Facebook',
      'prompt' => 'I want you to act as a high quality content writer. I will type a short topic and you will reply with a high converting social media post. This content will be used on Facebook. It should have a hook and high potential to go viral on social media. Don\'t use quotations at the beginning and end. Use sentence-case style. My topic is{topic}.',
    ),
    'Meta Title' => 
    array (
      'title' => 'Meta Title',
      'is_pro' => false,
      'fields' => 'Name, Description',
      'description' => 'Optimize your content for search engines with effective meta titles.',
      'prompt' => 'Write SEO friendly meta title for \'{description}\'. The title should be concise, accurate, and attention-grabbing, while also incorporating relevant keywords to improve search engine optimization. The website\\\'s brand name is \'{name}\'.',
    ),
    'Meta Description' => 
    array (
      'title' => 'Meta Description',
      'is_pro' => false,
      'fields' => 'Description',
      'description' => 'Create meta descriptions that increase click-through rates.',
      'prompt' => 'Write SEO friendly meta description for \'{description}\'. The description should be concise, accurate, and attention-grabbing, while also incorporating relevant keywords to improve search engine optimization. You must keep the descripion between 150-160 characters long. Don\'t exceed this character limit.',
    ),
    'Meta Keywords' => 
    array (
      'title' => 'Meta Keywords',
      'is_pro' => false,
      'fields' => 'Name, Description',
      'description' => 'Generate relevant keywords to improve SEO.',
      'prompt' => 'Write SEO friendly meta keywords for \'{description}\'. The keywords should be concise, accurate, and attention-grabbing to improve search engine optimization results. The website\\\'s brand name is \'{name}\'.',
    ),
    'Sales Page Headlines' => 
    array (
      'title' => 'Sales Page Headlines',
      'is_pro' => false,
      'fields' => 'Description, Tone',
      'description' => 'Create effective headlines for your sales pages.',
      'prompt' => 'I want you to act as a high quality sales page content writer. I will give a short description and you will reply with a high converting headline for sales page. It should have a persuasive hook that influence the reader to stay on that page. The headline should have easy to understand but catchy words. Don\'t use any quotations at the beginning and end of the headline. Don\'t use capital letter for all the words. Only show the final result. Don\'t add any extra element in the result. Use sentence-case style. My product description is{description}.',
    ),
    'Sentence Expander' => 
    array (
      'title' => 'Sentence Expander',
      'is_pro' => false,
      'fields' => 'Content, Tone, Word Count',
      'description' => 'Expand short sentences into detailed paragraphs.',
      'prompt' => 'Expand this content with more details: \'{content}\'. Generate with{tone} tone. Use better grammar and human friendly text so that it\'s easy to read and understand. The word limit should not exceed{word count} words.',
    ),
    'Button Call to Action Text' => 
    array (
      'title' => 'Button Call to Action Text',
      'is_pro' => false,
      'fields' => 'Description',
      'description' => 'Generate effective call-to-action text for your buttons.',
      'prompt' => 'Write Call to action button text for \'{description}\'. The CTA should be very persuasive and engaging so that readers feel urgency to take action immediately. You must leep the content short with maxium 4/5 words.',
    ),
    'Review Blog Post' => 
    array (
      'title' => 'Review Blog Post',
      'is_pro' => false,
      'markdown' => true,
      'number_of_results' => false,
      'fields' => 'Name, Description, Keywords, Tone, Word Count',
      'description' => 'Create informative and engaging reviews of products or services.',
      'prompt' => 'Write{tone} review blog post on \'{product name}\'.{description}. This blog post should mostly include the positive features of \'{product name}\'. But show some negative side if there is any. Use the best practices to make it SEO friendly. Keywords to include in the first paragraph and body:{keywords}. The output should be over{word count} words. OUTPUT: Markdown format with #Headings, ##H2, ###H3, + bullet points, + sub-bullet points',
    ),
    'Comparison Blog Post Between 2 Products' => 
    array (
      'title' => 'Comparison Blog Post Between 2 Products',
      'is_pro' => false,
      'number_of_results' => false,
      'markdown' => true,
      'fields' => 'Product 1, Product 2, Product 1 Description, Product 2 Description, Keywords, Tone, Word Count',
      'description' => 'Generate a complete blog post based on a given topic or keywords',
      'prompt' => 'Write{tone} comparison blog post between \'{product_1}\' and \'{product_2}\'.{description_1}.{description_2}. Show benefits of using both products. Add side by side comparison between these products and help the reader to decide to choose a better one based on their needs. While writing, always give priority to \'{product_1}\' more than \'{product_2}\' so that readers feel emotional about \'{product_1}\' and purchase it. Use the best practices to make it SEO friendly. The output should be over{word count} words. OUTPUT: Markdown format with #Headings, ##H2, ###H3, + bullet points, + sub-bullet points',
    ),
    'WooCommerce Product Description' => 
    array (
      'title' => 'WooCommerce Product Description',
      'is_pro' => false,
      'markdown' => true,
      'number_of_results' => false,
      'fields' => 'Name, Description, Tone',
      'description' => 'Optimize your content for search engines with effective meta titles.',
      'prompt' => 'Write{tone} product description for \'{name}\'.{description}. Include features, benefits inside the description with paragraph & bullet points.',
    ),
    'Amazon Product Review' => 
    array (
      'title' => 'Amazon Product Review',
      'is_pro' => false,
      'markdown' => true,
      'number_of_results' => false,
      'fields' => 'Name, Description',
      'description' => 'Create engaging reviews for Amazon products.',
      'prompt' => 'Write SEO friendly review for a \'{name}\' sold on Amazon. Product\'s major features are:{description}. Make sure the article is engaging and human friendly. Create a few problems related to if not using this product and then present this product as a solution of each specific problem. Focus on why using this product can make the reader\\\'s life easier and better. Use short paragraphs so that it\\\'s easy to read. The article must be over{word count} words.',
    ),
    'Email Subject Line' => 
    array (
      'title' => 'Email Subject Line',
      'is_pro' => false,
      'fields' => 'Topic, Tone',
      'description' => 'Create subject lines that increase email open rates.',
      'prompt' => 'Write an email subject line for \'{topic}\'. The subject line should be{tone}, attention-grabbing and concise, with a maximum of 10 words. It should entice the reader to open the email and learn more about the offer. Additionally, the subject line should convey a sense of urgency and encourage the reader to take advantage of the discount before it expires.',
    ),
    'Email Content' => 
    array (
      'title' => 'Email Content',
      'is_pro' => false,
      'fields' => 'Subject, Description, Tone',
      'description' => 'Generate compelling email content that drives engagement.',
      'prompt' => 'Write an email content on \'{subject}\'. Detail description: \'{description}\'. The email should be{tone}, engaging, and concise, with a maximum of 150 words. It should start by grabbing the reader\'s attention in an exciting and intriguing way. The email should then clearly explain the details of the \'{description}\'. Give focus on Benefits instead of showing features. Finally, the email should include a strong call-to-action that encourages the reader to take the desired action.',
    ),
    'FAQs Writer' => 
    array (
      'title' => 'FAQs Writer',
      'is_pro' => false,
      'fields' => 'Topic, Tone',
      'description' => 'Quickly create informative FAQs for your website or product.',
      'prompt' => 'Write a set of FAQs (Frequently Asked Questions) for \'{topic}\'. The FAQs should be{tone}. The FAQs should provide answers to common questions that potential users may have about \'{topic}\', with a focus on explaining features, benefits, and usage. The FAQs should be organized in a clear and easy-to-read format, with each question and answer presented in a separate section. Additionally, the language used should be concise, jargon-free, and accessible to a general audience. The goal of the FAQs is to provide potential users with the information they need to decide whether \'{topic}\' is the right tool for their needs.',
    ),
    'Grammar Correction' => 
    array (
      'title' => 'Grammar Correction',
      'is_pro' => false,
      'fields' => 'Content Text Area, Tone',
      'description' => 'Ensure your content is error-free with Grammar Correction.',
      'prompt' => 'Fix any possible grammar mistakes and improve the structure of this content:{content_textarea}',
    ),
    'Features to Benefits' => 
    array (
      'title' => 'Features to Benefits',
      'is_pro' => false,
      'fields' => 'Description',
      'description' => 'Highlight the benefits of your products or services.',
      'prompt' => 'Write a list of benefits for these features: \'{description}\'. The list should explain how these features can be beneficial to users in terms of solving their content creation challenges. Each feature-to-benefit conversion should be presented in a clear and concise manner and should not be more than 1 line. Additionally, the language used should be accessible to a general audience, with minimal technical jargon. The goal of the list is to help potential users understand the value of the subject. Don\'t use the word "benefit" before each sentence.',
    ),
    'HSO Copywriting Formula' => 
    array (
      'title' => 'HSO Copywriting Formula',
      'is_pro' => false,
      'fields' => 'Topic, Tone',
      'description' => 'Use the Headline, Story, Offer formula to create effective copy',
      'prompt' => 'Write a copy using the HSO (Hook, Story, Offer) copywriting formula for \'{topic}\'. The HSO copywriting formula is a framework that helps writers create effective and engaging copy by capturing the reader\'s attention with a hook, building a story that connects with the reader, and presenting an offer that encourages the reader to take action. The tone of the copy should be{tone}.',
    ),
    'AIDA Copywriting Formula' => 
    array (
      'title' => 'AIDA Copywriting Formula',
      'is_pro' => false,
      'fields' => 'Topic, Tone',
      'description' => 'Use the Attention, Interest, Desire, Action formula to write persuasive copy.',
      'prompt' => 'Write a copy using the AIDA copywriting formula for \'{topic}\'. The tone of the copy should be{tone}.',
    ),
    'PAS Copywriting Formula' => 
    array (
      'title' => 'PAS Copywriting Formula',
      'is_pro' => false,
      'fields' => 'Topic, Tone',
      'description' => 'Use the Problem, Agitate, Solve formula to write compelling copy.',
      'prompt' => 'Write a copy using the PAS (Problem, Agitate, Solve) copywriting formula for \'{topic}\'. The PAS copywriting formula is a framework that helps writers create effective and persuasive copy by identifying a problem that resonates with the reader, agitating that problem to create a sense of urgency, and presenting a solution that solves the problem and meets the reader\'s needs. The tone of the copy should be{tone}.',
    ),
    'Offer Ideas' => 
    array (
      'title' => 'Offer Ideas',
      'is_pro' => false,
      'fields' => 'Topic',
      'description' => 'Generate new ideas for offers and promotions.',
      'prompt' => 'Create a list of offer ideas for \'{topic}\'. Each offer idea should be presented in a clear and compelling manner, with a focus on the unique value proposition of \'{topic}\' and the benefits that customers can expect to receive. The goal of the list is to provide a range of actionable and effective offer ideas that can be used to drive engagement and conversions for \'{topic}\'.',
    ),
    'Press Release' => 
    array (
      'title' => 'Press Release',
      'is_pro' => false,
      'fields' => 'Topic, Tone',
      'description' => 'Write effective press releases that get your message out.',
      'prompt' => 'Write a press release for a given \'{topic}\'. A press release is a written communication that announces a newsworthy event or development, such as a product launch, new partnership, or company milestone. The press release should be written in a clear and concise style, with a focus on presenting the key information in an engaging and newsworthy way. The tone of the press release should be{tone}. The goal of the press release is to generate media interest and coverage, as well as to inform and engage stakeholders about the topic at hand.',
    ),
    'Social Media Post Ideas' => 
    array (
      'title' => 'Social Media Post Ideas',
      'is_pro' => false,
      'fields' => 'Topic',
      'description' => 'Generate ideas for engaging social media posts.',
      'prompt' => 'Create a list of social media post ideas for \'{topic}\'. Each post idea should be presented in a clear and engaging manner, with a focus on the brand\'s unique value proposition and the interests and needs of its target audience. The goal of the list is to provide a range of actionable and effective social media post ideas that can be used to drive engagement and awareness for the brand or topic. The list should include a variety of post types, such as text posts, image posts, video posts, and other multimedia formats.',
    ),
    'Website Tagline' => 
    array (
      'title' => 'Website Tagline',
      'is_pro' => false,
      'fields' => 'Topic',
      'description' => 'Create memorable taglines for your website.',
      'prompt' => 'Craft a website tagline for \'{topic}\'. The tagline should be short, memorable, and impactful, capturing the essence of the brand or website in a single phrase or sentence. It should also be aligned with the brand\'s values, mission, and target audience, and should communicate a clear and compelling message about the brand or website\'s unique value proposition. The goal of the tagline is to differentiate the brand or website from its competitors, build brand awareness and recognition, and engage and resonate with its target audience.',
    ),
    'Website About Us' => 
    array (
      'title' => 'Website About Us',
      'is_pro' => false,
      'fields' => 'Topic',
      'description' => 'Quickly create compelling About Us pages.',
      'prompt' => 'Write an "About Us" page content for \'{topic}\'. The "About Us" page should be engaging, informative, and reflective of the brand\'s personality and voice. It should also be structured in a clear and logical manner, with a focus on highlighting the most important and compelling aspects of the brand or website. The page should provide a clear and concise overview of the brand\'s history, mission, and values, as well as its products or services and the benefits they offer to customers. Additionally, it should communicate the brand\'s unique value proposition and differentiate it from its competitors. The goal of the "About Us" page is to build trust and credibility with the brand\'s target audience, and to communicate its story and value in a compelling and memorable way.',
    ),
    'Quora Answers' => 
    array (
      'title' => 'Quora Answers',
      'is_pro' => false,
      'markdown' => true,
      'fields' => 'Question',
      'description' => 'Generate informative answers to common questions on Quora.',
      'prompt' => 'Using Markdown formatting, write a 100% unique, creative and in a human-like style answers to this Quora question: \'{question}\'. Your answer should be well-researched, informative, and engaging, providing value to the question asker and other readers. It should be structured in a clear and logical manner, with a focus on answering the question thoroughly and providing additional insights or context where appropriate. Your answer should also be written in a clear and concise manner, with attention given to grammar, spelling, and overall readability. Always use a combination of paragraphs and lists. The goal of the Quora answer is to provide a helpful and valuable response to the question asker, and to potentially drive traffic and engagement to the website or brand being promoted.',
    ),
    'Comment Reply' => 
    array (
      'title' => 'Comment Reply',
      'is_pro' => false,
      'fields' => 'Comment, Tone',
      'description' => 'Quickly respond to comments on your blog or social media.',
      'prompt' => 'Write a{tone} reply to this comment: \'comment\' on the website. Your reply should be well-informed, informative, and engaging, providing value to the commenter and other readers. It should be structured in a clear and logical manner, with a focus on addressing the specific points made in the comment and providing additional insights or context where appropriate. Your reply should also be written in a clear and concise manner, with attention given to grammar, spelling, and overall readability. The goal of the reply is to provide a helpful and valuable response to the commenter, and to potentially build relationships, engagement, and authority in the online community. Make the comment as much shorter as possible.',
    ),
    'Feature List' => 
    array (
      'title' => 'Feature List',
      'is_pro' => false,
      'fields' => 'Topic',
      'description' => 'Generate comprehensive lists of product or service features.',
      'prompt' => 'Write a list of features on \'{topic}\'. The feature list should be engaging. Additionally, the language used should be accessible to a general audience, with minimal technical jargon. The goal of the list is to help potential users understand the value of the subject.',
    ),
    'Course Name' => 
    array (
      'title' => 'Course Name',
      'is_pro' => false,
      'fields' => 'Topic',
      'description' => 'Generate catchy names for your online courses.',
      'prompt' => 'Write an engaging course name on \'{topic}\'.',
    ),
    'Course Description' => 
    array (
      'title' => 'Course Description',
      'is_pro' => false,
      'fields' => 'Name, Description, Tone',
      'description' => 'Create compelling descriptions for your online courses.',
      'prompt' => 'Write a{tone} course description on \'{name}\'. Details of the course: \'{description}\'. It should start by grabbing the reader\'s attention in an exciting and intriguing way. The language used should be concise, jargon-free, and accessible to a general audience. ',
    ),
    'Feature Details' => 
    array (
      'title' => 'Feature Details',
      'is_pro' => false,
      'fields' => 'Topic, Tone',
      'description' => 'Quickly describe the features of your products or services.',
      'prompt' => 'Write a short description on \'{topic}\'. It should start by grabbing the reader\'s attention in an exciting and intriguing way. The language used should be concise, jargon-free, and accessible to a general audience. Keep it short and simple. The tone of the copy should be{tone}.',
    ),
    'Keyword Generator' => 
    array (
      'title' => 'Keyword Generator',
      'is_pro' => false,
      'fields' => 'Topic',
      'description' => 'Generate relevant keywords to improve SEO.',
      'prompt' => 'Generate multiple keywords based on \'{topic}\'. These keywords will be used to improve search engine optimization results.',
    ),
    'Content Rewriter' => 
    array (
      'title' => 'Content Rewriter',
      'is_pro' => false,
      'fields' => 'Content Text Area',
      'description' => 'Quickly rewrite existing content to improve readability and SEO.',
      'prompt' => 'Rewrite{tone} of \'{content_textarea}\'. Use better grammar and human friendly text so that it\'s easy to read and understand.',
    ),
    'Magic Headlines ' => 
    array (
      'title' => 'Magic Headlines ',
      'is_pro' => false,
      'fields' => 'Topic',
      'description' => 'Generate hundreds of headline ideas in seconds.',
      'prompt' => 'I want you to act as a high quality content writer. I will type a title, or keywords via comma and you will reply with a high converting headline. The headline should have a hook and high potential to go viral on social media. Don\'t use quotations at the beginning and end. Don\'t use capital letter for all the words. Use sentence-case style. My keyword is{topic}.',
    ),
  ),
  'pro' => 
  array (
    'One Click Blog Post' => 
    array (
      'title' => 'One Click Blog Post',
      'markdown' => true,
      'number_of_results' => false,
      'is_pro' => false,
      'fields' => 'Topic, Keywords, Tone, Word Count',
      'description' => 'Generate complete blog posts that engage your readers.',
      'prompt' => 'Using Markdown formatting, you must write a 100% unique, creative and in a human-like style article using headings and sub-headings. The generated result must be over 4000 words. There should be minimum 20 headings and 10 sub-headings in the content. Write article on “{topic}“. Be detailed as much as possible and cover the full topic. Try to write at least 800-1000 words content for each heading or sub-heading. You must add Table of contents with links. Try to use contractions, idioms, transitional phrases, interjections, dangling modifiers, and colloquialisms, and avoiding repetitive phrases and unnatural sentence structures. The article should include SEO meta-description (must include “{topic},{keywords}” in the description), introduction, a click-worthy short title. Also, Use the seed keyword as the first H2. Always use a combination of paragraphs, lists, and tables for a better reader experience. Write at least one paragraph with heading “{topic}“. Write down at least 6 faqs with answers and conclusion. Make sure the article is plagiarism free. Don\'t forget to use a question mark (?) at the end of questions. Try not to change the original topic: “{topic}” while writing the Title. You must use “{topic},{keywords}” 2-3 times in article. Try to include “{topic}” in headings as well. Write a content which can easily pass every AI detection tools test.',
    ),
    'Blog Post Writer' => 
    array (
      'title' => 'Blog Post Writer',
      'markdown' => true,
      'is_pro' => false,
      'number_of_results' => false,
      'fields' => 'Topic, Keywords, Content Text Area, Tone',
      'description' => 'Generate a SEO friendly blog post with your basic instructions',
      'prompt' => 'Using Markdown formatting, write a 100% unique, creative and human-like SEO-friendly blog post of over 3000 words using headings and sub-headings. My topic is: \'{topic}\' and you must follow this outline or description: \'{content_textarea}\'. You must write at least 2-3 paragraphs with 300-400 words content for each outline title. You must add a Table of Contents at the beginning. Try to use contractions, idioms, transitional phrases, interjections, dangling modifiers, and colloquialisms, and avoiding repetitive phrases and unnatural sentence structures. Also, use the seed keyword as the first H2. Always use a combination of paragraphs, lists, and tables for a better reader experience. Write down at least 5-7 faqs with answers and conclusion. Make sure the article is plagiarism free. Try to use \'{topic},{keywords}\' as keywords 2-3 times in the article. The final result must pass ChatGPT detection and AI content detection.',
    ),
    'Review Blog Post' => 
    array (
      'title' => 'Review Blog Post',
      'is_pro' => false,
      'markdown' => true,
      'number_of_results' => false,
      'fields' => 'Name, Description, Keywords, Tone, Word Count',
      'description' => 'Create informative and engaging reviews of products or services.',
      'prompt' => 'Write{tone} review blog post on \'{product name}\'.{description}. This blog post should mostly include the positive features of \'{product name}\'. But show some negative side if there is any. Use the best practices to make it SEO friendly. Keywords to include in the first paragraph and body:{keywords}. The output should be over{word count} words. OUTPUT: Markdown format with #Headings, ##H2, ###H3, + bullet points, + sub-bullet points',
    ),
    'Comparison Blog Post Between 2 Products' => 
    array (
      'title' => 'Comparison Blog Post Between 2 Products',
      'is_pro' => false,
      'number_of_results' => false,
      'markdown' => true,
      'fields' => 'Product 1, Product 2, Product 1 Description, Product 2 Description, Keywords, Tone, Word Count',
      'description' => 'Generate a complete blog post based on a given topic or keywords',
      'prompt' => 'Write{tone} comparison blog post between \'{product_1}\' and \'{product_2}\'.{description_1}.{description_2}. Show benefits of using both products. Add side by side comparison between these products and help the reader to decide to choose a better one based on their needs. While writing, always give priority to \'{product_1}\' more than \'{product_2}\' so that readers feel emotional about \'{product_1}\' and purchase it. Use the best practices to make it SEO friendly. The output should be over{word count} words. OUTPUT: Markdown format with #Headings, ##H2, ###H3, + bullet points, + sub-bullet points',
    ),
    'WooCommerce Product Description' => 
    array (
      'title' => 'WooCommerce Product Description',
      'is_pro' => false,
      'markdown' => true,
      'number_of_results' => false,
      'fields' => 'Name, Description, Tone',
      'description' => 'Optimize your content for search engines with effective meta titles.',
      'prompt' => 'Write{tone} product description for \'{name}\'.{description}. Include features, benefits inside the description with paragraph & bullet points.',
    ),
    'Amazon Product Review' => 
    array (
      'title' => 'Amazon Product Review',
      'is_pro' => false,
      'markdown' => true,
      'number_of_results' => false,
      'fields' => 'Name, Description',
      'description' => 'Create engaging reviews for Amazon products.',
      'prompt' => 'Write SEO friendly review for a \'{name}\' sold on Amazon. Product\'s major features are:{description}. Make sure the article is engaging and human friendly. Create a few problems related to if not using this product and then present this product as a solution of each specific problem. Focus on why using this product can make the reader\\\'s life easier and better. Use short paragraphs so that it\\\'s easy to read. The article must be over{word count} words.',
    ),
    'Meta Description' => 
    array (
      'title' => 'Meta Description',
      'is_pro' => false,
      'fields' => 'Description',
      'description' => 'Create meta descriptions that increase click-through rates.',
      'prompt' => 'Write SEO friendly meta description for \'{description}\'. The description should be concise, accurate, and attention-grabbing, while also incorporating relevant keywords to improve search engine optimization. You must keep the descripion between 150-160 characters long. Don\'t exceed this character limit.',
    ),
    'Email Subject Line' => 
    array (
      'title' => 'Email Subject Line',
      'is_pro' => false,
      'fields' => 'Topic, Tone',
      'description' => 'Create subject lines that increase email open rates.',
      'prompt' => 'Write an email subject line for \'{topic}\'. The subject line should be{tone}, attention-grabbing and concise, with a maximum of 10 words. It should entice the reader to open the email and learn more about the offer. Additionally, the subject line should convey a sense of urgency and encourage the reader to take advantage of the discount before it expires.',
    ),
    'Email Content' => 
    array (
      'title' => 'Email Content',
      'is_pro' => false,
      'fields' => 'Subject, Description, Tone',
      'description' => 'Generate compelling email content that drives engagement.',
      'prompt' => 'Write an email content on \'{subject}\'. Detail description: \'{description}\'. The email should be{tone}, engaging, and concise, with a maximum of 150 words. It should start by grabbing the reader\'s attention in an exciting and intriguing way. The email should then clearly explain the details of the \'{description}\'. Give focus on Benefits instead of showing features. Finally, the email should include a strong call-to-action that encourages the reader to take the desired action.',
    ),
    'FAQs Writer' => 
    array (
      'title' => 'FAQs Writer',
      'is_pro' => false,
      'fields' => 'Topic, Tone',
      'description' => 'Quickly create informative FAQs for your website or product.',
      'prompt' => 'Write a set of FAQs (Frequently Asked Questions) for \'{topic}\'. The FAQs should be{tone}. The FAQs should provide answers to common questions that potential users may have about \'{topic}\', with a focus on explaining features, benefits, and usage. The FAQs should be organized in a clear and easy-to-read format, with each question and answer presented in a separate section. Additionally, the language used should be concise, jargon-free, and accessible to a general audience. The goal of the FAQs is to provide potential users with the information they need to decide whether \'{topic}\' is the right tool for their needs.',
    ),
    'Grammar Correction' => 
    array (
      'title' => 'Grammar Correction',
      'is_pro' => false,
      'fields' => 'Content Text Area, Tone',
      'description' => 'Ensure your content is error-free with Grammar Correction.',
      'prompt' => 'Fix any possible grammar mistakes and improve the structure of this content:{content_textarea}',
    ),
    'Features to Benefits' => 
    array (
      'title' => 'Features to Benefits',
      'is_pro' => false,
      'fields' => 'Description',
      'description' => 'Highlight the benefits of your products or services.',
      'prompt' => 'Write a list of benefits for these features: \'{description}\'. The list should explain how these features can be beneficial to users in terms of solving their content creation challenges. Each feature-to-benefit conversion should be presented in a clear and concise manner and should not be more than 1 line. Additionally, the language used should be accessible to a general audience, with minimal technical jargon. The goal of the list is to help potential users understand the value of the subject. Don\'t use the word "benefit" before each sentence.',
    ),
    'HSO Copywriting Formula' => 
    array (
      'title' => 'HSO Copywriting Formula',
      'is_pro' => false,
      'fields' => 'Topic, Tone',
      'description' => 'Use the Headline, Story, Offer formula to create effective copy',
      'prompt' => 'Write a copy using the HSO (Hook, Story, Offer) copywriting formula for \'{topic}\'. The HSO copywriting formula is a framework that helps writers create effective and engaging copy by capturing the reader\'s attention with a hook, building a story that connects with the reader, and presenting an offer that encourages the reader to take action. The tone of the copy should be{tone}.',
    ),
    'AIDA Copywriting Formula' => 
    array (
      'title' => 'AIDA Copywriting Formula',
      'is_pro' => false,
      'fields' => 'Topic, Tone',
      'description' => 'Use the Attention, Interest, Desire, Action formula to write persuasive copy.',
      'prompt' => 'Write a copy using the AIDA copywriting formula for \'{topic}\'. The tone of the copy should be{tone}.',
    ),
    'PAS Copywriting Formula' => 
    array (
      'title' => 'PAS Copywriting Formula',
      'is_pro' => false,
      'fields' => 'Topic, Tone',
      'description' => 'Use the Problem, Agitate, Solve formula to write compelling copy.',
      'prompt' => 'Write a copy using the PAS (Problem, Agitate, Solve) copywriting formula for \'{topic}\'. The PAS copywriting formula is a framework that helps writers create effective and persuasive copy by identifying a problem that resonates with the reader, agitating that problem to create a sense of urgency, and presenting a solution that solves the problem and meets the reader\'s needs. The tone of the copy should be{tone}.',
    ),
    'Offer Ideas' => 
    array (
      'title' => 'Offer Ideas',
      'is_pro' => false,
      'fields' => 'Topic',
      'description' => 'Generate new ideas for offers and promotions.',
      'prompt' => 'Create a list of offer ideas for \'{topic}\'. Each offer idea should be presented in a clear and compelling manner, with a focus on the unique value proposition of \'{topic}\' and the benefits that customers can expect to receive. The goal of the list is to provide a range of actionable and effective offer ideas that can be used to drive engagement and conversions for \'{topic}\'.',
    ),
    'Press Release' => 
    array (
      'title' => 'Press Release',
      'is_pro' => false,
      'fields' => 'Topic, Tone',
      'description' => 'Write effective press releases that get your message out.',
      'prompt' => 'Write a press release for a given \'{topic}\'. A press release is a written communication that announces a newsworthy event or development, such as a product launch, new partnership, or company milestone. The press release should be written in a clear and concise style, with a focus on presenting the key information in an engaging and newsworthy way. The tone of the press release should be{tone}. The goal of the press release is to generate media interest and coverage, as well as to inform and engage stakeholders about the topic at hand.',
    ),
    'Social Media Post Ideas' => 
    array (
      'title' => 'Social Media Post Ideas',
      'is_pro' => false,
      'fields' => 'Topic',
      'description' => 'Generate ideas for engaging social media posts.',
      'prompt' => 'Create a list of social media post ideas for \'{topic}\'. Each post idea should be presented in a clear and engaging manner, with a focus on the brand\'s unique value proposition and the interests and needs of its target audience. The goal of the list is to provide a range of actionable and effective social media post ideas that can be used to drive engagement and awareness for the brand or topic. The list should include a variety of post types, such as text posts, image posts, video posts, and other multimedia formats.',
    ),
    'Website Tagline' => 
    array (
      'title' => 'Website Tagline',
      'is_pro' => false,
      'fields' => 'Topic',
      'description' => 'Create memorable taglines for your website.',
      'prompt' => 'Craft a website tagline for \'{topic}\'. The tagline should be short, memorable, and impactful, capturing the essence of the brand or website in a single phrase or sentence. It should also be aligned with the brand\'s values, mission, and target audience, and should communicate a clear and compelling message about the brand or website\'s unique value proposition. The goal of the tagline is to differentiate the brand or website from its competitors, build brand awareness and recognition, and engage and resonate with its target audience.',
    ),
    'Website About Us' => 
    array (
      'title' => 'Website About Us',
      'is_pro' => false,
      'fields' => 'Topic',
      'description' => 'Quickly create compelling About Us pages.',
      'prompt' => 'Write an "About Us" page content for \'{topic}\'. The "About Us" page should be engaging, informative, and reflective of the brand\'s personality and voice. It should also be structured in a clear and logical manner, with a focus on highlighting the most important and compelling aspects of the brand or website. The page should provide a clear and concise overview of the brand\'s history, mission, and values, as well as its products or services and the benefits they offer to customers. Additionally, it should communicate the brand\'s unique value proposition and differentiate it from its competitors. The goal of the "About Us" page is to build trust and credibility with the brand\'s target audience, and to communicate its story and value in a compelling and memorable way.',
    ),
    'Quora Answers' => 
    array (
      'title' => 'Quora Answers',
      'is_pro' => false,
      'markdown' => true,
      'fields' => 'Question',
      'description' => 'Generate informative answers to common questions on Quora.',
      'prompt' => 'Using Markdown formatting, write a 100% unique, creative and in a human-like style answers to this Quora question: \'{question}\'. Your answer should be well-researched, informative, and engaging, providing value to the question asker and other readers. It should be structured in a clear and logical manner, with a focus on answering the question thoroughly and providing additional insights or context where appropriate. Your answer should also be written in a clear and concise manner, with attention given to grammar, spelling, and overall readability. Always use a combination of paragraphs and lists. The goal of the Quora answer is to provide a helpful and valuable response to the question asker, and to potentially drive traffic and engagement to the website or brand being promoted.',
    ),
    'Comment Reply' => 
    array (
      'title' => 'Comment Reply',
      'is_pro' => false,
      'fields' => 'Comment, Tone',
      'description' => 'Quickly respond to comments on your blog or social media.',
      'prompt' => 'Write a{tone} reply to this comment: \'comment\' on the website. Your reply should be well-informed, informative, and engaging, providing value to the commenter and other readers. It should be structured in a clear and logical manner, with a focus on addressing the specific points made in the comment and providing additional insights or context where appropriate. Your reply should also be written in a clear and concise manner, with attention given to grammar, spelling, and overall readability. The goal of the reply is to provide a helpful and valuable response to the commenter, and to potentially build relationships, engagement, and authority in the online community. Make the comment as much shorter as possible.',
    ),
    'Feature List' => 
    array (
      'title' => 'Feature List',
      'is_pro' => false,
      'fields' => 'Topic',
      'description' => 'Generate comprehensive lists of product or service features.',
      'prompt' => 'Write a list of features on \'{topic}\'. The feature list should be engaging. Additionally, the language used should be accessible to a general audience, with minimal technical jargon. The goal of the list is to help potential users understand the value of the subject.',
    ),
    'Course Name' => 
    array (
      'title' => 'Course Name',
      'is_pro' => false,
      'fields' => 'Topic',
      'description' => 'Generate catchy names for your online courses.',
      'prompt' => 'Write an engaging course name on \'{topic}\'.',
    ),
    'Course Description' => 
    array (
      'title' => 'Course Description',
      'is_pro' => false,
      'fields' => 'Name, Description, Tone',
      'description' => 'Create compelling descriptions for your online courses.',
      'prompt' => 'Write a{tone} course description on \'{name}\'. Details of the course: \'{description}\'. It should start by grabbing the reader\'s attention in an exciting and intriguing way. The language used should be concise, jargon-free, and accessible to a general audience. ',
    ),
    'Feature Details' => 
    array (
      'title' => 'Feature Details',
      'is_pro' => false,
      'fields' => 'Topic, Tone',
      'description' => 'Quickly describe the features of your products or services.',
      'prompt' => 'Write a short description on \'{topic}\'. It should start by grabbing the reader\'s attention in an exciting and intriguing way. The language used should be concise, jargon-free, and accessible to a general audience. Keep it short and simple. The tone of the copy should be{tone}.',
    ),
    'Keyword Generator' => 
    array (
      'title' => 'Keyword Generator',
      'is_pro' => false,
      'fields' => 'Topic',
      'description' => 'Generate relevant keywords to improve SEO.',
      'prompt' => 'Generate multiple keywords based on \'{topic}\'. These keywords will be used to improve search engine optimization results.',
    ),
    'Linkedin Post' => 
    array (
      'title' => 'Linkedin Post',
      'is_pro' => false,
      'fields' => 'Topic',
      'description' => 'Generate a highly engaging post for Linkedin',
      'prompt' => 'I want you to act as a high quality content writer. I will type a short topic and you will reply with a high converting social media post. This content will be used on Linkedin. Linkedin is professional networking site. So, the content should be professionl. It should have a hook and high potential to go viral on social media. Don\'t use quotations at the beginning and end. Use sentence-case style. My topic is{topic}.',
    ),
    'Facebook Post' => 
    array (
      'title' => 'Facebook Post',
      'is_pro' => false,
      'fields' => 'Topic',
      'description' => 'Generate a highly engaging post for Facebook',
      'prompt' => 'I want you to act as a high quality content writer. I will type a short topic and you will reply with a high converting social media post. This content will be used on Facebook. It should have a hook and high potential to go viral on social media. Don\'t use quotations at the beginning and end. Use sentence-case style. My topic is{topic}.',
    ),
    'Content Rewriter' => 
    array (
      'title' => 'Content Rewriter',
      'is_pro' => false,
      'fields' => 'Content Text Area',
      'description' => 'Quickly rewrite existing content to improve readability and SEO.',
      'prompt' => 'Rewrite{tone} of \'{content_textarea}\'. Use better grammar and human friendly text so that it\'s easy to read and understand.',
    ),
    'Magic Headlines ' => 
    array (
      'title' => 'Magic Headlines ',
      'is_pro' => false,
      'fields' => 'Topic',
      'description' => 'Generate hundreds of headline ideas in seconds.',
      'prompt' => 'I want you to act as a high quality content writer. I will type a title, or keywords via comma and you will reply with a high converting headline. The headline should have a hook and high potential to go viral on social media. Don\'t use quotations at the beginning and end. Don\'t use capital letter for all the words. Use sentence-case style. My keyword is{topic}.',
    ),
    'Product Description' => 
    array (
      'title' => 'Product Description',
      'is_pro' => false,
      'fields' => 'Name, Description, Tone',
      'description' => 'Generate compelling product descriptions for your online store.',
      'prompt' => 'I want you to act as a high quality content writer. I will type a Product name, and short description and you will reply with highly engaging Product Description which will be used to sell this product. This description must have hooks so that readers feel connected. You must include features, benefits of using this product using paragraphs & bullet points. Writing tone should be{tone}. My product name is{name}. And a short details about my product is:{description}.',
    ),
  ),
);
