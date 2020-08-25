#/bin/bash

pandoc \
    --toc \
    -f gfm+fenced_code_blocks+space_in_atx_header+strikeout+backtick_code_blocks+pipe_tables \
    --include-in-header header.tex \
    --pdf-engine=xelatex \
    -o PHPlib.pdf \
    PHPlib.md || true
