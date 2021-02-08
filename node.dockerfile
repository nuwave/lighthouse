FROM node:14-slim

ARG USER_ID
ARG GROUP_ID

RUN if [ ${USER_ID:-0} -ne 0 ] && [ ${GROUP_ID:-0} -ne 0 ]; then \
    groupmod --gid ${GROUP_ID} --non-unique node && \
    usermod --uid ${USER_ID} --gid ${GROUP_ID} node \
;fi

USER node
