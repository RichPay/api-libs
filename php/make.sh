#/bin/bash

pandoc --template=GitHub.html5 -t html5 -o PHPlib.pdf -f gfm+pipe_tables+fenced_code_blocks+space_in_atx_header+strikeout+backtick_code_blocks PHPlib.md || true
