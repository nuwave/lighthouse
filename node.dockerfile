FROM node:slim

ARG USER_ID
ARG GROUP_ID

RUN if [ ${USER_ID:-0} -ne 0 ] && [ ${GROUP_ID:-0} -ne 0 ]; then \
    groupmod -g ${GROUP_ID} node && \
    usermod -u ${USER_ID} -g ${GROUP_ID} node \
;fi

USER node
