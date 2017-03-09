#email-validation

a WordPress plugin to verify the legitimacy of an email address without actually sending an email.

Every mail server is configured differently, in the way it responds to this type of request. As a result, there are situations when an email may look legit when it's actually not. This plugin starts by identifying email address that are obviously invalid and those whose domain do not even have MX records in their DNS. Then the tricky/fun part begins...

Ultimately, the true test is to just send the email and see if it bounced. However, the purpose of this plugin is to weed out as many invalid email addresses as possible without doing that in order to cut down on bounced emails negatively affecting your mail server's reputataion.
